<?php
// ============================================================
// STATE COORDINATOR - CREATE INCIDENT
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

// Get state name
$state_name = 'Unknown State';
if (!empty($state_id)) {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
}

// Get LGAs
$lgas = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$state_id]);
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

// Get elections
$elections = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, type, status 
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL
        AND (states_json LIKE ? OR states_json IS NULL OR states_json = '[]')
        ORDER BY election_date DESC
    ");
    $stmt->execute([$tenant_id, '%"' . $state_id . '"%']);
    $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching elections: " . $e->getMessage());
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

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $incident_type = $_POST['incident_type'] ?? '';
    $severity = $_POST['severity'] ?? 'medium';
    $description = trim($_POST['description'] ?? '');
    $election_id = isset($_POST['election_id']) ? (int)$_POST['election_id'] : 0;
    $lga_id = isset($_POST['lga_id']) ? (int)$_POST['lga_id'] : 0;
    $pu_name = trim($_POST['pu_name'] ?? '');
    $gps_lat = $_POST['gps_lat'] ?? null;
    $gps_lng = $_POST['gps_lng'] ?? null;
    $is_panic = isset($_POST['is_panic']) ? 1 : 0;
    
    if (empty($title)) {
        $error = 'Please enter a title.';
    } elseif (empty($incident_type)) {
        $error = 'Please select an incident type.';
    } elseif (empty($description)) {
        $error = 'Please enter a description.';
    } elseif ($lga_id <= 0) {
        $error = 'Please select an LGA.';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO incidents (
                    tenant_id, state_id, reporter_id, lga_id, election_id,
                    incident_type, severity, is_panic, title, description,
                    gps_lat, gps_lng, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'reported', NOW())
            ");
            
            $stmt->execute([
                $tenant_id,
                $state_id,
                $user_id,
                $lga_id,
                $election_id ?: null,
                $incident_type,
                $severity,
                $is_panic,
                $title,
                $description,
                $gps_lat ?: null,
                $gps_lng ?: null
            ]);
            
            $incident_id = $db->lastInsertId();
            
            logActivity($user_id, 'incident_created', 
                "Created incident: $title (ID: $incident_id)",
                'incidents', $incident_id
            );
            
            $message = "Incident reported successfully!";
            
            // Redirect to view the incident
            header('Location: incident-view.php?id=' . $incident_id . '&created=1');
            exit();
            
        } catch (Exception $e) {
            $error = 'Failed to report incident: ' . $e->getMessage();
            error_log("Incident creation error: " . $e->getMessage());
        }
    }
}

$page_title = 'Report Incident';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.incident-container {
    max-width: 700px;
    margin: 0 auto;
}

.form-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
    margin-bottom: 16px;
}

.form-card .card-title {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.form-card .card-title i {
    color: var(--primary);
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

.form-group input[type="text"],
.form-group input[type="number"],
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    transition: var(--transition);
    background: white;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}

.form-group .help-text {
    font-size: 0.65rem;
    color: var(--gray-400);
    margin-top: 4px;
}

.form-group .checkbox-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-group .checkbox-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #EF4444;
}

.form-group .checkbox-group label {
    font-weight: 600;
    font-size: 0.82rem;
    color: #DC2626;
    cursor: pointer;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
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

.btn-group .btn-submit {
    background: #EF4444;
    color: white;
}

.btn-group .btn-submit:hover {
    background: #DC2626;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
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

.panic-warning {
    background: #FEF2F2;
    border: 1px solid #FECACA;
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 16px;
    color: #991B1B;
    display: none;
}

.panic-warning i {
    margin-right: 6px;
}

@media (max-width: 768px) {
    .form-card {
        padding: 16px 18px;
    }
    .form-row {
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
        <div class="incident-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-plus-circle" style="color:#EF4444;"></i> Report Incident</h1>
                    <p class="subtitle">
                        <i class="fas fa-flag"></i> 
                        <?php echo htmlspecialchars($state_name); ?> State - Report a New Incident
                    </p>
                </div>
                <div class="actions">
                    <a href="incidents.php" class="btn-secondary-sm">
                        <i class="fas fa-arrow-left"></i> Back to Incidents
                    </a>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="form-card">
                <div class="card-title"><i class="fas fa-info-circle"></i> Incident Information</div>

                <div class="panic-warning" id="panicWarning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>⚠️ Panic Alert!</strong> This incident will be marked as urgent and escalated immediately.
                </div>

                <form method="POST" action="" id="incidentForm">
                    <div class="form-group">
                        <label>Is this a Panic/Emergency?</label>
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_panic" id="isPanic" onchange="togglePanic()" />
                            <label for="isPanic"><i class="fas fa-exclamation-circle"></i> This is a panic/emergency situation</label>
                        </div>
                        <div class="help-text">Check this if you need immediate assistance</div>
                    </div>

                    <div class="form-group">
                        <label>Title <span class="required">*</span></label>
                        <input type="text" name="title" required placeholder="Brief title of the incident..." value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" />
                    </div>

                    <div class="form-group">
                        <label>Incident Type <span class="required">*</span></label>
                        <select name="incident_type" required>
                            <option value="">Select type...</option>
                            <?php foreach ($incident_types as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($_POST['incident_type'] ?? '') === $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Severity <span class="required">*</span></label>
                        <select name="severity" required>
                            <?php foreach ($severity_options as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($_POST['severity'] ?? 'medium') === $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>LGA <span class="required">*</span></label>
                            <select name="lga_id" required>
                                <option value="">Select LGA...</option>
                                <?php foreach ($lgas as $lga): ?>
                                    <option value="<?php echo $lga['id']; ?>" <?php echo ($_POST['lga_id'] ?? '') == $lga['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lga['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Election (Optional)</label>
                            <select name="election_id">
                                <option value="0">None</option>
                                <?php foreach ($elections as $e): ?>
                                    <option value="<?php echo $e['id']; ?>" <?php echo ($_POST['election_id'] ?? 0) == $e['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($e['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Polling Unit (Optional)</label>
                        <input type="text" name="pu_name" placeholder="Enter polling unit name or location..." value="<?php echo htmlspecialchars($_POST['pu_name'] ?? ''); ?>" />
                        <div class="help-text">Provide the specific location if known</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>GPS Latitude</label>
                            <input type="text" name="gps_lat" placeholder="e.g., 6.5244" value="<?php echo htmlspecialchars($_POST['gps_lat'] ?? ''); ?>" />
                            <div class="help-text">Optional - from your device</div>
                        </div>
                        <div class="form-group">
                            <label>GPS Longitude</label>
                            <input type="text" name="gps_lng" placeholder="e.g., 3.3792" value="<?php echo htmlspecialchars($_POST['gps_lng'] ?? ''); ?>" />
                            <div class="help-text">Optional - from your device</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Description <span class="required">*</span></label>
                        <textarea name="description" required placeholder="Provide a detailed description of the incident..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="btn-group">
                        <a href="incidents.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-exclamation-triangle"></i> Report Incident
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
function togglePanic() {
    var checkbox = document.getElementById('isPanic');
    var warning = document.getElementById('panicWarning');
    if (checkbox.checked) {
        warning.style.display = 'block';
        // Set severity to critical automatically
        document.querySelector('select[name="severity"]').value = 'critical';
    } else {
        warning.style.display = 'none';
    }
}

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