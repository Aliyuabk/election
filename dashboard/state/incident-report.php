<?php
// ============================================================
// STATE COORDINATOR - INCIDENT REPORT (CREATE)
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

// Only state coordinator can access
if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

// If state_id is not set in session, try to get it from user record
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

// ============================================================
// GENERATE CSRF TOKEN
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ============================================================
// FETCH STATE NAME
// ============================================================
$state_name = 'Unknown State';
try {
    if (!empty($state_id)) {
        $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
        $stmt->execute([$state_id]);
        $state = $stmt->fetch(PDO::FETCH_ASSOC);
        $state_name = $state['name'] ?? 'Unknown State';
    }
} catch (Exception $e) {
    error_log("Error fetching state: " . $e->getMessage());
}

// ============================================================
// FETCH ELECTIONS, LGAS, WARDS, PUS
// ============================================================
$elections = [];
$lgas = [];
$wards = [];
$polling_units = [];

try {
    // Elections
    $stmt = $db->prepare("
        SELECT id, name, status 
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL 
        AND (states_json LIKE ? OR states_json IS NULL OR states_json = '[]')
        ORDER BY election_date DESC
    ");
    $stmt->execute([$tenant_id, '%"' . $state_id . '"%']);
    $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // LGAs
    $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$state_id]);
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Wards
    $stmt = $db->prepare("SELECT id, name, lga_id FROM wards WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Polling Units
    $stmt = $db->prepare("SELECT id, name, code, ward_id FROM polling_units WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching data: " . $e->getMessage());
}

// ============================================================
// INCIDENT TYPES AND SEVERITY
// ============================================================
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

$severity_levels = [
    'low' => 'Low',
    'medium' => 'Medium',
    'high' => 'High',
    'critical' => 'Critical'
];

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$error = '';
$success = '';
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Security validation failed. Please try again.';
    } else {
        $form_data = [
            'election_id' => !empty($_POST['election_id']) ? (int)$_POST['election_id'] : null,
            'incident_type' => $_POST['incident_type'] ?? '',
            'severity' => $_POST['severity'] ?? 'medium',
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'lga_id' => !empty($_POST['lga_id']) ? (int)$_POST['lga_id'] : null,
            'ward_id' => !empty($_POST['ward_id']) ? (int)$_POST['ward_id'] : null,
            'pu_id' => !empty($_POST['pu_id']) ? (int)$_POST['pu_id'] : null,
            'is_panic' => isset($_POST['is_panic']) ? 1 : 0,
            'status' => 'reported'
        ];
        
        $errors = [];
        
        if (empty($form_data['title'])) {
            $errors[] = 'Title is required.';
        }
        if (empty($form_data['description'])) {
            $errors[] = 'Description is required.';
        }
        if (empty($form_data['incident_type'])) {
            $errors[] = 'Incident type is required.';
        }
        if (empty($form_data['lga_id'])) {
            $errors[] = 'Please select an LGA.';
        }
        
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO incidents (
                        tenant_id, election_id, reporter_id,
                        incident_type, severity, title, description,
                        state_id, lga_id, ward_id, pu_id,
                        is_panic, status, created_at
                    ) VALUES (
                        ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, NOW()
                    )
                ");
                
                $stmt->execute([
                    $tenant_id,
                    $form_data['election_id'],
                    $user_id,
                    $form_data['incident_type'],
                    $form_data['severity'],
                    $form_data['title'],
                    $form_data['description'],
                    $state_id,
                    $form_data['lga_id'],
                    $form_data['ward_id'] ?: null,
                    $form_data['pu_id'] ?: null,
                    $form_data['is_panic'],
                    $form_data['status']
                ]);
                
                $incident_id = $db->lastInsertId();
                
                logActivity($user_id, 'incident_reported', "Reported incident: {$form_data['title']} (ID: $incident_id)");
                
                $success = "Incident reported successfully!";
                $form_data = [];
                
            } catch (Exception $e) {
                $error = 'Error reporting incident: ' . $e->getMessage();
                error_log("Incident report error: " . $e->getMessage());
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

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
    margin: 0;
}
.page-header h2 small {
    font-size: 0.8rem;
    font-weight: 400;
    color: var(--gray-500);
    display: block;
    margin-top: 2px;
}

.btn-secondary-sm {
    padding: 8px 20px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.btn-secondary-sm:hover {
    background: var(--gray-200);
}

.form-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 28px 32px;
    box-shadow: var(--shadow);
}
.form-container .form-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.form-container .form-title i {
    color: var(--danger);
}
.form-container .form-subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--gray-100);
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px 24px;
}
.form-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.form-group.full-width {
    grid-column: 1 / -1;
}
.form-group label {
    font-weight: 600;
    font-size: 0.82rem;
    color: var(--gray-700);
}
.form-group label .required {
    color: var(--danger);
    margin-left: 2px;
}
.form-group .help-text {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 2px;
}
.form-group input,
.form-group select,
.form-group textarea {
    padding: 10px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    transition: var(--transition);
    background: var(--gray-50);
    color: var(--gray-700);
    width: 100%;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}
.form-group textarea {
    resize: vertical;
    min-height: 100px;
}
.form-group .checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-top: 6px;
}
.form-group .checkbox-group input[type="checkbox"] {
    width: 20px;
    height: 20px;
    accent-color: var(--danger);
    cursor: pointer;
    flex-shrink: 0;
}
.form-group .checkbox-group label {
    font-weight: 400;
    cursor: pointer;
    font-size: 0.85rem;
}
.form-group .checkbox-group label i {
    color: var(--danger);
}

.form-section-title {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--gray-700);
    grid-column: 1 / -1;
    padding-top: 8px;
    border-bottom: 1px solid var(--gray-100);
    padding-bottom: 8px;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.form-section-title i {
    color: var(--danger);
    font-size: 0.85rem;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--gray-200);
    flex-wrap: wrap;
}
.form-actions .btn {
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
.form-actions .btn-danger {
    background: var(--danger);
    color: white;
}
.form-actions .btn-danger:hover {
    background: #DC2626;
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(239, 68, 68, 0.25);
}
.form-actions .btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.form-actions .btn-secondary:hover {
    background: var(--gray-200);
}

.error-message {
    background: #FEF2F2;
    color: #DC2626;
    padding: 14px 18px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
    border: 1px solid #FECACA;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.error-message i {
    margin-top: 2px;
    font-size: 1.1rem;
}
.success-message {
    background: #ECFDF5;
    color: #065F46;
    padding: 14px 18px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
    border: 1px solid #A7F3D0;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.success-message i {
    margin-top: 2px;
    font-size: 1.1rem;
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .form-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .form-container {
        padding: 20px;
    }
    .form-actions {
        flex-direction: column;
    }
    .form-actions .btn {
        justify-content: center;
        width: 100%;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-exclamation-triangle" style="color:var(--danger);margin-right:8px;"></i>
                    Report Incident
                    <small>Report an incident in <?php echo htmlspecialchars($state_name); ?></small>
                </h2>
            </div>
            <div>
                <a href="incidents.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Incidents
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $success; ?></div>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="form-container">
            <div class="form-title">
                <i class="fas fa-flag"></i> Incident Report Form
            </div>
            <div class="form-subtitle">
                Fill in the details below to report an incident. All fields marked with <span class="required">*</span> are required.
            </div>
            
            <form method="POST" action="" id="incidentForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="form-grid">
                    <!-- Incident Details -->
                    <div class="form-section-title">
                        <i class="fas fa-info-circle"></i> Incident Details
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="title">Title <span class="required">*</span></label>
                        <input type="text" name="title" id="title" placeholder="e.g., Violence at Polling Unit" value="<?php echo htmlspecialchars($form_data['title'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="description">Description <span class="required">*</span></label>
                        <textarea name="description" id="description" placeholder="Provide detailed description of the incident..." required><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="incident_type">Incident Type <span class="required">*</span></label>
                        <select name="incident_type" id="incident_type" required>
                            <option value="">Select Type</option>
                            <?php foreach ($incident_types as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($form_data['incident_type'] ?? '') === $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="severity">Severity <span class="required">*</span></label>
                        <select name="severity" id="severity" required>
                            <option value="">Select Severity</option>
                            <?php foreach ($severity_levels as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($form_data['severity'] ?? '') === $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Location -->
                    <div class="form-section-title">
                        <i class="fas fa-map-marker-alt"></i> Location
                    </div>
                    
                    <div class="form-group">
                        <label for="election_id">Related Election</label>
                        <select name="election_id" id="election_id">
                            <option value="">None</option>
                            <?php foreach ($elections as $e): ?>
                                <option value="<?php echo $e['id']; ?>" <?php echo ($form_data['election_id'] ?? 0) == $e['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($e['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="lga_id">LGA <span class="required">*</span></label>
                        <select name="lga_id" id="lga_id" required>
                            <option value="">Select LGA</option>
                            <?php foreach ($lgas as $l): ?>
                                <option value="<?php echo $l['id']; ?>" <?php echo ($form_data['lga_id'] ?? 0) == $l['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($l['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="ward_id">Ward</label>
                        <select name="ward_id" id="ward_id">
                            <option value="">Select Ward</option>
                            <?php foreach ($wards as $w): ?>
                                <option value="<?php echo $w['id']; ?>" <?php echo ($form_data['ward_id'] ?? 0) == $w['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($w['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="pu_id">Polling Unit</label>
                        <select name="pu_id" id="pu_id">
                            <option value="">Select Polling Unit</option>
                            <?php foreach ($polling_units as $pu): ?>
                                <option value="<?php echo $pu['id']; ?>" <?php echo ($form_data['pu_id'] ?? 0) == $pu['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pu['name']); ?> (<?php echo htmlspecialchars($pu['code'] ?? ''); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Additional -->
                    <div class="form-section-title">
                        <i class="fas fa-flag"></i> Additional Information
                    </div>
                    
                    <div class="form-group full-width">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_panic" id="is_panic" value="1" <?php echo isset($form_data['is_panic']) && $form_data['is_panic'] ? 'checked' : ''; ?>>
                            <label for="is_panic">
                                <i class="fas fa-bell"></i> This is a <strong style="color:var(--danger);">PANIC</strong> situation - immediate attention required
                            </label>
                        </div>
                        <div class="help-text">Check this if the incident requires urgent response.</div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-flag"></i> Report Incident
                    </button>
                    <a href="incidents.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() {
            preloader.style.display = 'none';
        }, 600);
    }
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

// ============================================================
// SIDEBAR DROPDOWNS
// ============================================================
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

// ============================================================
// PROFILE DROPDOWN
// ============================================================
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

// ============================================================
// DYNAMIC WARD LOADING
// ============================================================
document.getElementById('lga_id').addEventListener('change', function() {
    var lgaId = this.value;
    var wardSelect = document.getElementById('ward_id');
    var puSelect = document.getElementById('pu_id');
    
    wardSelect.innerHTML = '<option value="">Loading...</option>';
    puSelect.innerHTML = '<option value="">Select Polling Unit</option>';
    
    if (lgaId) {
        fetch('ajax/get-wards.php?lga_id=' + lgaId)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                wardSelect.innerHTML = '<option value="">Select Ward</option>';
                data.forEach(function(ward) {
                    var option = document.createElement('option');
                    option.value = ward.id;
                    option.textContent = ward.name;
                    wardSelect.appendChild(option);
                });
            })
            .catch(function() {
                wardSelect.innerHTML = '<option value="">Error loading wards</option>';
            });
    } else {
        wardSelect.innerHTML = '<option value="">Select Ward</option>';
    }
});

document.getElementById('ward_id').addEventListener('change', function() {
    var wardId = this.value;
    var puSelect = document.getElementById('pu_id');
    
    puSelect.innerHTML = '<option value="">Loading...</option>';
    
    if (wardId) {
        fetch('ajax/get-polling-units.php?ward_id=' + wardId)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                puSelect.innerHTML = '<option value="">Select Polling Unit</option>';
                data.forEach(function(pu) {
                    var option = document.createElement('option');
                    option.value = pu.id;
                    option.textContent = pu.name + ' (' + pu.code + ')';
                    puSelect.appendChild(option);
                });
            })
            .catch(function() {
                puSelect.innerHTML = '<option value="">Error loading polling units</option>';
            });
    } else {
        puSelect.innerHTML = '<option value="">Select Polling Unit</option>';
    }
});

// ============================================================
// FORM VALIDATION
// ============================================================
document.getElementById('incidentForm').addEventListener('submit', function(e) {
    var title = document.getElementById('title');
    var description = document.getElementById('description');
    var type = document.getElementById('incident_type');
    var lga = document.getElementById('lga_id');
    var isValid = true;
    
    document.querySelectorAll('.error').forEach(function(el) {
        el.classList.remove('error');
    });
    
    if (!title.value.trim()) {
        title.classList.add('error');
        isValid = false;
    }
    if (!description.value.trim()) {
        description.classList.add('error');
        isValid = false;
    }
    if (!type.value) {
        type.classList.add('error');
        isValid = false;
    }
    if (!lga.value) {
        lga.classList.add('error');
        isValid = false;
    }
    
    if (!isValid) {
        e.preventDefault();
        var firstError = document.querySelector('.error');
        if (firstError) {
            firstError.focus();
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
});
</script>
</body>
</html>