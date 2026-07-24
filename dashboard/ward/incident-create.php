<?php
// ============================================================
// WARD COORDINATOR - CREATE INCIDENT
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

// Only Ward coordinator can access
if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$ward_id = SessionManager::get('ward_id');
$lga_id = SessionManager::get('lga_id');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

// If ward_id is not set in session, try to get it from user record
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

// ============================================================
// FETCH WARD NAME
// ============================================================
$ward_name = 'Ward';
try {
    if ($ward_id) {
        $stmt = $db->prepare("SELECT name FROM wards WHERE id = ?");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching ward name: " . $e->getMessage());
}

// ============================================================
// FETCH POLLING UNITS
// ============================================================
$polling_units = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, code FROM polling_units 
        WHERE ward_id = ? AND is_active = 1 
        ORDER BY name ASC
    ");
    $stmt->execute([$ward_id]);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching polling units: " . $e->getMessage());
}

// ============================================================
// HANDLE INCIDENT CREATION
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $incident_type = isset($_POST['incident_type']) ? $_POST['incident_type'] : 'other';
    $severity = isset($_POST['severity']) ? $_POST['severity'] : 'medium';
    $pu_id = isset($_POST['pu_id']) ? (int)$_POST['pu_id'] : 0;
    $is_panic = isset($_POST['is_panic']) ? 1 : 0;
    $gps_lat = isset($_POST['gps_lat']) ? (float)$_POST['gps_lat'] : null;
    $gps_lng = isset($_POST['gps_lng']) ? (float)$_POST['gps_lng'] : null;
    
    if (empty($title) || empty($description)) {
        $error_message = "Please fill in both title and description.";
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO incidents (
                    tenant_id, election_id, reporter_id, pu_id, ward_id, lga_id, state_id,
                    incident_type, severity, is_panic, title, description, gps_lat, gps_lng,
                    status, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?,
                    'reported', NOW()
                )
            ");
            
            // Get active election
            $election_stmt = $db->prepare("
                SELECT id FROM elections 
                WHERE tenant_id = ? AND status = 'active' 
                AND JSON_CONTAINS(wards_json, JSON_QUOTE(?))
                LIMIT 1
            ");
            $election_stmt->execute([$tenant_id, $ward_id]);
            $election = $election_stmt->fetch(PDO::FETCH_ASSOC);
            $election_id = $election ? $election['id'] : null;
            
            $stmt->execute([
                $tenant_id,
                $election_id,
                $user_id,
                $pu_id > 0 ? $pu_id : null,
                $ward_id,
                $lga_id,
                $state_id,
                $incident_type,
                $severity,
                $is_panic,
                $title,
                $description,
                $gps_lat,
                $gps_lng
            ]);
            
            $incident_id = $db->lastInsertId();
            
            logActivity($user_id, 'incident_created', "Created incident: $title (ID: $incident_id)", 'incidents', $incident_id);
            
            $success_message = "Incident reported successfully!";
            header('Location: incidents.php?success=' . urlencode($success_message));
            exit();
            
        } catch (Exception $e) {
            $error_message = "Error creating incident: " . $e->getMessage();
            error_log("Incident creation error: " . $e->getMessage());
        }
    }
}

$page_title = 'Report Incident';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.incident-form {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
    max-width: 700px;
}
.incident-form .form-group {
    margin-bottom: 16px;
}
.incident-form .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.incident-form .form-group label .required {
    color: #EF4444;
}
.incident-form .form-group input[type="text"],
.incident-form .form-group textarea,
.incident-form .form-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
}
.incident-form .form-group textarea {
    resize: vertical;
    min-height: 100px;
    font-family: inherit;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--gray-200);
}

.alert {
    padding: 12px 16px;
    border-radius: var(--radius);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success {
    background: #ECFDF5;
    border: 1px solid #D1FAE5;
    color: #065F46;
}
.alert-danger {
    background: #FEF2F2;
    border: 1px solid #FEE2E2;
    color: #991B1B;
}
.alert i {
    font-size: 1.1rem;
}

.panic-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 0;
}
.panic-toggle input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: #EF4444;
}
.panic-toggle label {
    font-weight: 600;
    color: #EF4444;
    cursor: pointer;
}

@media (max-width: 768px) {
    .incident-form {
        max-width: 100%;
    }
    .form-row {
        grid-template-columns: 1fr;
    }
    .form-actions {
        flex-direction: column;
    }
    .form-actions button,
    .form-actions a {
        width: 100%;
        text-align: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="broadcast-header">
            <div>
                <h2><i class="fas fa-exclamation-triangle"></i> Report Incident</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                </p>
            </div>
            <div>
                <a href="incidents.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Incidents
                </a>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Incident Form -->
        <div class="incident-form">
            <form method="POST" action="" id="incidentForm">
                <div class="form-group">
                    <label>Incident Title <span class="required">*</span></label>
                    <input type="text" name="title" id="title" placeholder="Enter incident title..." required>
                </div>

                <div class="form-group">
                    <label>Description <span class="required">*</span></label>
                    <textarea name="description" id="description" placeholder="Describe the incident in detail..." required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Incident Type <span class="required">*</span></label>
                        <select name="incident_type" required>
                            <option value="">Select Type</option>
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
                        <label>Severity <span class="required">*</span></label>
                        <select name="severity" required>
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Polling Unit</label>
                    <select name="pu_id">
                        <option value="0">-- Select Polling Unit --</option>
                        <?php foreach ($polling_units as $pu): ?>
                            <option value="<?php echo $pu['id']; ?>">
                                <?php echo htmlspecialchars($pu['name']); ?> (<?php echo htmlspecialchars($pu['code']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="panic-toggle">
                    <input type="checkbox" name="is_panic" id="is_panic" value="1">
                    <label for="is_panic"><i class="fas fa-exclamation-circle"></i> This is a PANIC alert (critical emergency)</label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Report Incident
                    </button>
                    <button type="reset" class="btn-secondary">
                        <i class="fas fa-undo"></i> Clear
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
// Validate form
document.getElementById('incidentForm').addEventListener('submit', function(e) {
    const title = document.getElementById('title').value.trim();
    const description = document.getElementById('description').value.trim();
    
    if (!title) {
        e.preventDefault();
        alert('Please enter an incident title.');
        return false;
    }
    if (!description) {
        e.preventDefault();
        alert('Please enter an incident description.');
        return false;
    }
    return true;
});

// Preloader
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

// Sidebar toggle
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

// Sidebar dropdowns
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

// Profile dropdown
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