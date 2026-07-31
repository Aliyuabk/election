<?php
// ============================================================
// FEDERAL CONSTITUENCY COORDINATOR - CREATE INCIDENT
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'federal_constituency') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$constituency_id = SessionManager::get('federal_constituency_id');
$state_id = SessionManager::get('state_id');

$db = getDB();

// ============================================================
// GET LGA IDs FROM CONSTITUENCY
// ============================================================
$lga_ids = [];
try {
    if ($constituency_id) {
        $stmt = $db->prepare("SELECT lgas_json FROM federal_constituencies WHERE id = ?");
        $stmt->execute([$constituency_id]);
        $lgas_json = $stmt->fetchColumn();
        
        if ($lgas_json) {
            $lga_names = json_decode($lgas_json, true) ?: [];
            
            if (!empty($lga_names)) {
                $placeholders = implode(',', array_fill(0, count($lga_names), '?'));
                $stmt = $db->prepare("SELECT id FROM lgas WHERE name IN ($placeholders) AND state_id = ? AND is_active = 1");
                $stmt->execute(array_merge($lga_names, [$state_id]));
                $lga_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        }
    }
} catch (Exception $e) {
    error_log("Error getting LGA IDs: " . $e->getMessage());
}

$lga_list = !empty($lga_ids) ? implode(',', array_map('intval', $lga_ids)) : '0';

// ============================================================
// GET LGAS, WARDS, AND PUS
// ============================================================
$lgas = [];
$wards = [];
$pus = [];

try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE id IN ($lga_list) ORDER BY name ASC");
        $stmt->execute();
        $lgas = $stmt->fetchAll();
        
        $stmt = $db->prepare("SELECT w.id, w.name, l.name as lga_name FROM wards w JOIN lgas l ON w.lga_id = l.id WHERE w.lga_id IN ($lga_list) ORDER BY l.name ASC, w.name ASC");
        $stmt->execute();
        $wards = $stmt->fetchAll();
        
        $stmt = $db->prepare("SELECT pu.id, pu.name, pu.code, w.name as ward_name, l.name as lga_name FROM polling_units pu JOIN wards w ON pu.ward_id = w.id JOIN lgas l ON w.lga_id = l.id WHERE w.lga_id IN ($lga_list) ORDER BY l.name ASC, w.name ASC, pu.name ASC");
        $stmt->execute();
        $pus = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching locations: " . $e->getMessage());
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $incident_type = $_POST['incident_type'] ?? '';
    $severity = $_POST['severity'] ?? 'medium';
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $lga_id = isset($_POST['lga_id']) ? (int)$_POST['lga_id'] : 0;
    $ward_id = isset($_POST['ward_id']) ? (int)$_POST['ward_id'] : 0;
    $pu_id = isset($_POST['pu_id']) ? (int)$_POST['pu_id'] : 0;
    $is_panic = isset($_POST['is_panic']) ? 1 : 0;
    
    if (empty($incident_type)) {
        $error = 'Please select an incident type.';
    } elseif (empty($title)) {
        $error = 'Please enter a title.';
    } elseif (empty($description)) {
        $error = 'Please enter a description.';
    } elseif ($lga_id <= 0) {
        $error = 'Please select an LGA.';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO incidents (
                    tenant_id, election_id, reporter_id, pu_id, ward_id, lga_id, state_id,
                    incident_type, severity, is_panic, title, description,
                    status, created_at, updated_at
                ) VALUES (
                    ?, NULL, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    'reported', NOW(), NOW()
                )
            ");
            $stmt->execute([
                $tenant_id,
                $user_id,
                $pu_id ?: null,
                $ward_id ?: null,
                $lga_id,
                $state_id,
                $incident_type,
                $severity,
                $is_panic,
                $title,
                $description
            ]);
            
            $incident_id = $db->lastInsertId();
            
            logActivity($user_id, 'incident_created', "Created incident: $title (ID: $incident_id)", 'incident', $incident_id);
            
            if ($is_panic) {
                logSecurityEvent($user_id, 'panic_incident', "Panic incident reported: $title (ID: $incident_id)", 80);
            }
            
            header('Location: incident-details.php?id=' . $incident_id . '&success=1');
            exit();
            
        } catch (Exception $e) {
            $error = 'Error reporting incident: ' . $e->getMessage();
            error_log("Incident create error: " . $e->getMessage());
        }
    }
}

$page_title = 'Report Incident';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.form-container {
    max-width: 800px;
    margin: 0 auto;
}
.form-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 30px;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.82rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.form-group label .required {
    color: #DC2626;
}
.form-group .help-text {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 4px;
}
.form-group select,
.form-group input,
.form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    transition: var(--transition);
    background: var(--gray-50);
    color: var(--gray-700);
}
.form-group select:focus,
.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}
.form-group textarea {
    min-height: 120px;
    resize: vertical;
}
.form-group .checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
}
.form-group .checkbox-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}
.form-group .checkbox-group label {
    margin: 0;
    cursor: pointer;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.btn {
    padding: 10px 24px;
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
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-error {
    background: #FEF2F2;
    color: #DC2626;
    border: 1px solid #FECACA;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    .form-card {
        padding: 16px;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="form-container">
            <h2 style="font-size:1.3rem;font-weight:700;margin-bottom:4px;">
                <i class="fas fa-exclamation-triangle" style="color:var(--primary);"></i> Report Incident
            </h2>
            <p style="color:var(--gray-500);font-size:0.9rem;margin-bottom:20px;">
                Report an incident in your federal constituency.
            </p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <div class="form-card">
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Incident Type <span class="required">*</span></label>
                            <select name="incident_type" required>
                                <option value="">Select Type...</option>
                                <option value="violence">Violence</option>
                                <option value="intimidation">Intimidation</option>
                                <option value="ballot_stuffing">Ballot Stuffing</option>
                                <option value="vote_buying">Vote Buying</option>
                                <option value="voter_suppression">Voter Suppression</option>
                                <option value="material_shortage">Material Shortage</option>
                                <option value="delay">Delay</option>
                                <option value="technical_issue">Technical Issue</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Severity</label>
                            <select name="severity">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Title <span class="required">*</span></label>
                        <input type="text" name="title" placeholder="Brief title of the incident" required>
                    </div>

                    <div class="form-group">
                        <label>Description <span class="required">*</span></label>
                        <textarea name="description" placeholder="Detailed description of what happened" required></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>LGA <span class="required">*</span></label>
                            <select name="lga_id" id="lga_id" required>
                                <option value="">Select LGA...</option>
                                <?php foreach ($lgas as $lga): ?>
                                    <option value="<?php echo $lga['id']; ?>">
                                        <?php echo htmlspecialchars($lga['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Ward</label>
                            <select name="ward_id" id="ward_id">
                                <option value="">Select Ward...</option>
                                <?php foreach ($wards as $ward): ?>
                                    <option value="<?php echo $ward['id']; ?>" data-lga="<?php echo $ward['lga_id']; ?>">
                                        <?php echo htmlspecialchars($ward['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Polling Unit</label>
                        <select name="pu_id" id="pu_id">
                            <option value="">Select Polling Unit...</option>
                            <?php foreach ($pus as $pu): ?>
                                <option value="<?php echo $pu['id']; ?>" data-ward="<?php echo $pu['ward_id']; ?>">
                                    <?php echo htmlspecialchars($pu['name']); ?> (<?php echo htmlspecialchars($pu['code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_panic" id="is_panic" value="1">
                            <label for="is_panic">
                                <strong style="color:#DC2626;">🚨 This is a PANIC incident</strong>
                                <span style="font-weight:400;color:var(--gray-500);font-size:0.8rem;display:block;">
                                    Check this if this is an emergency requiring immediate attention
                                </span>
                            </label>
                        </div>
                    </div>

                    <div style="display:flex;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-200);">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Report Incident
                        </button>
                        <a href="incidents.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
// Dynamic filtering
document.getElementById('lga_id').addEventListener('change', function() {
    var lgaId = this.value;
    var wardSelect = document.getElementById('ward_id');
    var puSelect = document.getElementById('pu_id');
    
    for (var i = 0; i < wardSelect.options.length; i++) {
        var option = wardSelect.options[i];
        if (option.value === '') continue;
        var optionLga = option.getAttribute('data-lga');
        option.style.display = (optionLga == lgaId || lgaId === '') ? '' : 'none';
    }
    wardSelect.value = '';
    puSelect.value = '';
});

document.getElementById('ward_id').addEventListener('change', function() {
    var wardId = this.value;
    var puSelect = document.getElementById('pu_id');
    
    for (var i = 0; i < puSelect.options.length; i++) {
        var option = puSelect.options[i];
        if (option.value === '') continue;
        var optionWard = option.getAttribute('data-ward');
        option.style.display = (optionWard == wardId || wardId === '') ? '' : 'none';
    }
    puSelect.value = '';
});

// Sidebar toggle (standard)
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