<?php
// ============================================================
// NATIONAL COORDINATOR - CREATE INCIDENT REPORT
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

// Only national coordinator can access
if (SessionManager::get('role_level') !== 'national') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');

// Get parameters for pre-filling
$pu_id = isset($_GET['pu']) ? intval($_GET['pu']) : 0;
$ward_id = isset($_GET['ward']) ? intval($_GET['ward']) : 0;
$lga_id = isset($_GET['lga']) ? intval($_GET['lga']) : 0;
$state_id = isset($_GET['state']) ? intval($_GET['state']) : 0;
$back_url = isset($_GET['back']) ? $_GET['back'] : 'incidents.php';

$db = getDB();

// ============================================================
// FETCH LOCATION DATA FOR DROPDOWNS
// ============================================================
$states = [];
$lgas = [];
$wards = [];
$polling_units = [];

try {
    // Get states
    $stmt = $db->prepare("SELECT id, name FROM states WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $states = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $states = [];
}

// If state is selected, get LGAs
if ($state_id > 0) {
    try {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$state_id]);
        $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $lgas = [];
    }
}

// If LGA is selected, get Wards
if ($lga_id > 0) {
    try {
        $stmt = $db->prepare("SELECT id, name FROM wards WHERE lga_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$lga_id]);
        $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $wards = [];
    }
}

// If Ward is selected, get Polling Units
if ($ward_id > 0) {
    try {
        $stmt = $db->prepare("SELECT id, name, code FROM polling_units WHERE ward_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$ward_id]);
        $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $polling_units = [];
    }
}

// If PU is selected, get its location info
$pre_filled_location = '';
if ($pu_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT 
                pu.name as pu_name,
                w.name as ward_name,
                l.name as lga_name,
                s.name as state_name
            FROM polling_units pu
            JOIN wards w ON pu.ward_id = w.id
            JOIN lgas l ON w.lga_id = l.id
            JOIN states s ON l.state_id = s.id
            WHERE pu.id = ?
        ");
        $stmt->execute([$pu_id]);
        $location = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($location) {
            $pre_filled_location = $location['pu_name'] . ' (' . $location['ward_name'] . ', ' . $location['lga_name'] . ', ' . $location['state_name'] . ')';
        }
    } catch (Exception $e) {
        $pre_filled_location = '';
    }
}

// ============================================================
// INCIDENT TYPES AND SEVERITY LEVELS
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
    'critical' => 'Critical - Immediate attention required',
    'high' => 'High - Urgent',
    'medium' => 'Medium - Important',
    'low' => 'Low - Monitor'
];

// ============================================================
// PROCESS FORM SUBMISSION
// ============================================================
$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $incident_type = $_POST['incident_type'] ?? '';
    $severity = $_POST['severity'] ?? 'medium';
    $state_id_post = intval($_POST['state_id'] ?? 0);
    $lga_id_post = intval($_POST['lga_id'] ?? 0);
    $ward_id_post = intval($_POST['ward_id'] ?? 0);
    $pu_id_post = intval($_POST['pu_id'] ?? 0);
    $is_panic = isset($_POST['is_panic']) ? 1 : 0;
    $status = $_POST['status'] ?? 'reported';
    $assigned_to = intval($_POST['assigned_to'] ?? 0);
    
    // Validation
    if (empty($title)) {
        $error = 'Please enter an incident title';
    } elseif (empty($description)) {
        $error = 'Please enter a description';
    } elseif (empty($incident_type)) {
        $error = 'Please select an incident type';
    } elseif ($state_id_post <= 0) {
        $error = 'Please select a state';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO incidents (
                    tenant_id, reporter_id, title, description,
                    incident_type, severity, is_panic, status,
                    state_id, lga_id, ward_id, pu_id,
                    assigned_to, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $tenant_id,
                $user_id,
                $title,
                $description,
                $incident_type,
                $severity,
                $is_panic,
                $status,
                $state_id_post,
                $lga_id_post > 0 ? $lga_id_post : null,
                $ward_id_post > 0 ? $ward_id_post : null,
                $pu_id_post > 0 ? $pu_id_post : null,
                $assigned_to > 0 ? $assigned_to : null
            ]);
            
            $incident_id = $db->lastInsertId();
            
            // Log activity
            $log_stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, entity_type, entity_id, created_at)
                VALUES (?, ?, 'incident_created', ?, 'incident', ?, NOW())
            ");
            $log_stmt->execute([
                $user_id,
                $tenant_id,
                "Reported incident: $title",
                $incident_id
            ]);
            
            $success = true;
            $message = "Incident reported successfully!";
            
            // Redirect if not AJAX
            header("Location: incidents.php?success=1");
            exit();
            
        } catch (Exception $e) {
            $error = 'Failed to report incident: ' . $e->getMessage();
            error_log("Incident Create Error: " . $e->getMessage());
        }
    }
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Report Incident';
$page_subtitle = 'Create a new incident report';
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Breadcrumb -->
        <div class="welcome-section">
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <a href="../national/index.php" style="text-decoration:none;color:var(--gray-500);">Dashboard</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="incidents.php" style="text-decoration:none;color:var(--gray-500);">Incidents</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Report Incident</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-exclamation-triangle" style="color:var(--danger);"></i>
                        Report Incident
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        Record an incident for tracking and resolution
                    </p>
                </div>
                <a href="incidents.php" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fas fa-arrow-left"></i> Back to Incidents
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($message && $success): ?>
            <div style="background:#D1FAE5;color:#065F46;padding:12px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #A7F3D0;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-check-circle" style="font-size:1.2rem;"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background:#FEE2E2;color:#991B1B;padding:12px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #FECACA;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-exclamation-circle" style="font-size:1.2rem;"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Incident Form -->
        <form method="POST" action="" style="background:white;border-radius:var(--radius);padding:24px;border:1px solid var(--gray-200);">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <!-- Left Column -->
                <div>
                    <!-- Title -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Incident Title <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="text" name="title" class="form-control" required
                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                               placeholder="Brief title of the incident"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    
                    <!-- Description -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Description <span style="color:#EF4444;">*</span>
                        </label>
                        <textarea name="description" class="form-control" required rows="6"
                                  placeholder="Detailed description of the incident..."
                                  style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;resize:vertical;transition:var(--transition);"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <div style="font-size:0.65rem;color:var(--gray-400);margin-top:4px;">
                            <span id="charCount">0</span> characters
                        </div>
                    </div>
                    
                    <!-- Incident Type -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Incident Type <span style="color:#EF4444;">*</span>
                        </label>
                        <select name="incident_type" class="form-control" required
                                style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                            <option value="">Select Type...</option>
                            <?php foreach ($incident_types as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($_POST['incident_type'] ?? '') == $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Severity -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Severity <span style="color:#EF4444;">*</span>
                        </label>
                        <select name="severity" class="form-control" required
                                style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                            <?php foreach ($severity_levels as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($_POST['severity'] ?? 'medium') == $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div>
                    <!-- Location -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Location <span style="color:#EF4444;">*</span>
                        </label>
                        
                        <!-- State -->
                        <div style="margin-bottom:8px;">
                            <select name="state_id" class="form-control" id="stateSelect" required
                                    style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;transition:var(--transition);">
                                <option value="">Select State...</option>
                                <?php foreach ($states as $state): ?>
                                    <option value="<?php echo $state['id']; ?>" 
                                        <?php echo ($_POST['state_id'] ?? $state_id) == $state['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($state['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- LGA -->
                        <div style="margin-bottom:8px;">
                            <select name="lga_id" class="form-control" id="lgaSelect"
                                    style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;transition:var(--transition);">
                                <option value="">Select LGA...</option>
                                <?php foreach ($lgas as $lga): ?>
                                    <option value="<?php echo $lga['id']; ?>" 
                                        <?php echo ($_POST['lga_id'] ?? $lga_id) == $lga['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lga['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Ward -->
                        <div style="margin-bottom:8px;">
                            <select name="ward_id" class="form-control" id="wardSelect"
                                    style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;transition:var(--transition);">
                                <option value="">Select Ward...</option>
                                <?php foreach ($wards as $ward): ?>
                                    <option value="<?php echo $ward['id']; ?>" 
                                        <?php echo ($_POST['ward_id'] ?? $ward_id) == $ward['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ward['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Polling Unit -->
                        <div>
                            <select name="pu_id" class="form-control" id="puSelect"
                                    style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;transition:var(--transition);">
                                <option value="">Select Polling Unit...</option>
                                <?php foreach ($polling_units as $pu): ?>
                                    <option value="<?php echo $pu['id']; ?>" 
                                        <?php echo ($_POST['pu_id'] ?? $pu_id) == $pu['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($pu['name']); ?> (<?php echo htmlspecialchars($pu['code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if ($pre_filled_location): ?>
                            <div style="margin-top:8px;padding:8px 12px;background:#F0FDF4;border-radius:8px;border:1px solid #A7F3D0;">
                                <span style="font-size:0.75rem;color:#065F46;">
                                    <i class="fas fa-check-circle"></i> Location pre-filled: <?php echo htmlspecialchars($pre_filled_location); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Panic Button -->
                    <div style="margin-bottom:16px;">
                        <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem;color:var(--gray-600);cursor:pointer;padding:8px 12px;background:#FEF2F2;border-radius:8px;border:1px solid #FECACA;">
                            <input type="checkbox" name="is_panic" value="1" <?php echo isset($_POST['is_panic']) ? 'checked' : ''; ?>>
                            <i class="fas fa-bell" style="color:#EF4444;"></i>
                            <span style="font-weight:600;color:#DC2626;">Panic Alert - Emergency</span>
                        </label>
                        <div style="font-size:0.65rem;color:var(--gray-400);margin-top:4px;margin-left:12px;">
                            <i class="fas fa-info-circle"></i> Panic alerts trigger immediate notifications
                        </div>
                    </div>
                    
                    <!-- Status -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Status
                        </label>
                        <select name="status" class="form-control"
                                style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                            <option value="reported" <?php echo ($_POST['status'] ?? '') == 'reported' ? 'selected' : ''; ?>>Reported</option>
                            <option value="acknowledged" <?php echo ($_POST['status'] ?? '') == 'acknowledged' ? 'selected' : ''; ?>>Acknowledged</option>
                            <option value="investigating" <?php echo ($_POST['status'] ?? '') == 'investigating' ? 'selected' : ''; ?>>Investigating</option>
                        </select>
                    </div>
                    
                    <!-- Assign To -->
                    <div>
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Assign To (Optional)
                        </label>
                        <select name="assigned_to" class="form-control"
                                style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                            <option value="">Unassigned</option>
                            <?php
                            try {
                                $stmt = $db->prepare("
                                    SELECT id, full_name, role_id FROM users 
                                    WHERE tenant_id = ? AND status = 'active'
                                    ORDER BY full_name ASC
                                ");
                                $stmt->execute([$tenant_id]);
                                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($users as $user):
                            ?>
                                <option value="<?php echo $user['id']; ?>" 
                                    <?php echo ($_POST['assigned_to'] ?? '') == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['full_name']); ?>
                                </option>
                            <?php 
                                endforeach;
                            } catch (Exception $e) {}
                            ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div style="display:flex;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-200);flex-wrap:wrap;">
                <button type="submit" class="btn-primary" style="padding:10px 32px;background:var(--primary);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-save"></i> Report Incident
                </button>
                <button type="reset" class="btn-secondary" style="padding:10px 32px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-weight:500;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <a href="incidents.php" class="btn-secondary" style="padding:10px 32px;background:transparent;color:var(--gray-500);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-family:'Inter',sans-serif;font-weight:500;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</main>

<style>
.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-secondary:hover {
    background: var(--gray-200);
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    div[style*="grid-template-columns:1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script>
// ============================================================
// CHAR COUNTER
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var textarea = document.querySelector('textarea[name="description"]');
    var charCount = document.getElementById('charCount');
    
    if (textarea && charCount) {
        textarea.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });
        charCount.textContent = textarea.value.length;
    }
});

// ============================================================
// LOCATION DROPDOWN CHAINING
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var stateSelect = document.getElementById('stateSelect');
    var lgaSelect = document.getElementById('lgaSelect');
    var wardSelect = document.getElementById('wardSelect');
    var puSelect = document.getElementById('puSelect');
    
    // State change - load LGAs
    if (stateSelect) {
        stateSelect.addEventListener('change', function() {
            var stateId = this.value;
            if (stateId) {
                fetch('ajax-get-lgas.php?state_id=' + stateId)
                    .then(response => response.json())
                    .then(data => {
                        lgaSelect.innerHTML = '<option value="">Select LGA...</option>';
                        data.forEach(function(lga) {
                            lgaSelect.innerHTML += '<option value="' + lga.id + '">' + lga.name + '</option>';
                        });
                        wardSelect.innerHTML = '<option value="">Select Ward...</option>';
                        puSelect.innerHTML = '<option value="">Select Polling Unit...</option>';
                    })
                    .catch(error => console.error('Error:', error));
            } else {
                lgaSelect.innerHTML = '<option value="">Select LGA...</option>';
                wardSelect.innerHTML = '<option value="">Select Ward...</option>';
                puSelect.innerHTML = '<option value="">Select Polling Unit...</option>';
            }
        });
    }
    
    // LGA change - load Wards
    if (lgaSelect) {
        lgaSelect.addEventListener('change', function() {
            var lgaId = this.value;
            if (lgaId) {
                fetch('ajax-get-wards.php?lga_id=' + lgaId)
                    .then(response => response.json())
                    .then(data => {
                        wardSelect.innerHTML = '<option value="">Select Ward...</option>';
                        data.forEach(function(ward) {
                            wardSelect.innerHTML += '<option value="' + ward.id + '">' + ward.name + '</option>';
                        });
                        puSelect.innerHTML = '<option value="">Select Polling Unit...</option>';
                    })
                    .catch(error => console.error('Error:', error));
            } else {
                wardSelect.innerHTML = '<option value="">Select Ward...</option>';
                puSelect.innerHTML = '<option value="">Select Polling Unit...</option>';
            }
        });
    }
    
    // Ward change - load Polling Units
    if (wardSelect) {
        wardSelect.addEventListener('change', function() {
            var wardId = this.value;
            if (wardId) {
                fetch('ajax-get-pus.php?ward_id=' + wardId)
                    .then(response => response.json())
                    .then(data => {
                        puSelect.innerHTML = '<option value="">Select Polling Unit...</option>';
                        data.forEach(function(pu) {
                            puSelect.innerHTML += '<option value="' + pu.id + '">' + pu.name + ' (' + pu.code + ')</option>';
                        });
                    })
                    .catch(error => console.error('Error:', error));
            } else {
                puSelect.innerHTML = '<option value="">Select Polling Unit...</option>';
            }
        });
    }
});

// ============================================================
// SIDEBAR TOGGLE, DROPDOWNS, PROFILE, SEARCH
// ============================================================
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