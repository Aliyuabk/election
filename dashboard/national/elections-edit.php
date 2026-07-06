<?php
// ============================================================
// NATIONAL COORDINATOR - EDIT ELECTION
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

// Get election ID
$election_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($election_id <= 0) {
    header('Location: elections.php?error=invalid_election');
    exit();
}

$db = getDB();

// ============================================================
// FETCH ELECTION DATA
// ============================================================
$election = null;

try {
    $stmt = $db->prepare("SELECT * FROM elections WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$election_id, $tenant_id]);
    $election = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$election) {
        header('Location: elections.php?error=election_not_found');
        exit();
    }
    
} catch (Exception $e) {
    error_log("Election Edit Error: " . $e->getMessage());
    header('Location: elections.php?error=database_error');
    exit();
}

// ============================================================
// FETCH DATA FOR DROPDOWNS
// ============================================================
$states = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM states WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $states = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $states = [];
}

$lgas = [];
try {
    $stmt = $db->prepare("SELECT id, name, state_id FROM lgas WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lgas = [];
}

$wards = [];
try {
    $stmt = $db->prepare("SELECT id, name, lga_id FROM wards WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $wards = [];
}

$polling_units = [];
try {
    $stmt = $db->prepare("SELECT id, name, code, ward_id FROM polling_units WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $polling_units = [];
}

// ============================================================
// GET CURRENT SELECTIONS
// ============================================================
$selected_states = json_decode($election['states_json'] ?? '[]', true);
$selected_lgas = json_decode($election['lgas_json'] ?? '[]', true);
$selected_wards = json_decode($election['wards_json'] ?? '[]', true);
$selected_pus = json_decode($election['pus_json'] ?? '[]', true);

// ============================================================
// ELECTION TYPES AND STATUSES
// ============================================================
$election_types = [
    'presidential' => 'Presidential',
    'governorship' => 'Governorship',
    'senatorial' => 'Senatorial',
    'house_of_reps' => 'House of Reps',
    'house_of_assembly' => 'House of Assembly',
    'lga_chairman' => 'LGA Chairman',
    'councillorship' => 'Councillorship',
    'party_primary' => 'Party Primary',
    'internal_party' => 'Internal Party'
];

$status_options = [
    'draft' => 'Draft',
    'upcoming' => 'Upcoming',
    'active' => 'Active',
    'closed' => 'Closed',
    'cancelled' => 'Cancelled',
    'archived' => 'Archived'
];

// ============================================================
// PROCESS FORM SUBMISSION
// ============================================================
$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? '';
    $cycle = trim($_POST['cycle'] ?? '');
    $election_date = $_POST['election_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $status = $_POST['status'] ?? 'draft';
    $description = trim($_POST['description'] ?? '');
    
    $selected_states = isset($_POST['states']) ? array_map('intval', (array)$_POST['states']) : [];
    $selected_lgas = isset($_POST['lgas']) ? array_map('intval', (array)$_POST['lgas']) : [];
    $selected_wards = isset($_POST['wards']) ? array_map('intval', (array)$_POST['wards']) : [];
    $selected_pus = isset($_POST['pus']) ? array_map('intval', (array)$_POST['pus']) : [];
    
    // Validation
    if (empty($name)) {
        $error = 'Election name is required';
    } elseif (empty($type)) {
        $error = 'Election type is required';
    } elseif (empty($election_date)) {
        $error = 'Election date is required';
    } elseif (empty($selected_states) && empty($selected_lgas) && empty($selected_wards) && empty($selected_pus)) {
        $error = 'Please select at least one location';
    } else {
        try {
            // Prepare JSON data
            $states_json = !empty($selected_states) ? json_encode($selected_states) : null;
            $lgas_json = !empty($selected_lgas) ? json_encode($selected_lgas) : null;
            $wards_json = !empty($selected_wards) ? json_encode($selected_wards) : null;
            $pus_json = !empty($selected_pus) ? json_encode($selected_pus) : null;
            
            $stmt = $db->prepare("
                UPDATE elections 
                SET name = ?,
                    type = ?,
                    cycle = ?,
                    election_date = ?,
                    start_time = ?,
                    end_time = ?,
                    status = ?,
                    description = ?,
                    states_json = ?,
                    lgas_json = ?,
                    wards_json = ?,
                    pus_json = ?,
                    updated_by = ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            
            $stmt->execute([
                $name,
                $type,
                $cycle,
                $election_date,
                $start_time ?: null,
                $end_time ?: null,
                $status,
                $description,
                $states_json,
                $lgas_json,
                $wards_json,
                $pus_json,
                $user_id,
                $election_id,
                $tenant_id
            ]);
            
            // Log activity
            $log_stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, entity_type, entity_id, created_at)
                VALUES (?, ?, 'election_updated', ?, 'election', ?, NOW())
            ");
            $log_stmt->execute([
                $user_id,
                $tenant_id,
                "Updated election: $name",
                $election_id
            ]);
            
            $success = true;
            $message = "Election updated successfully!";
            
            // Refresh election data
            $stmt = $db->prepare("SELECT * FROM elections WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$election_id, $tenant_id]);
            $election = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $error = 'Failed to update election: ' . $e->getMessage();
            error_log("Election Update Error: " . $e->getMessage());
        }
    }
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Edit Election';
$page_subtitle = $election['name'] ?? 'Election';
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
                <a href="elections.php" style="text-decoration:none;color:var(--gray-500);">Elections</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="election-view.php?id=<?php echo $election_id; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($election['name']); ?></a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Edit</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-edit" style="color:var(--primary);"></i>
                        Edit Election
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        Update election details and configuration
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="election-view.php?id=<?php echo $election_id; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back to View
                    </a>
                    <a href="elections.php" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-list"></i> All Elections
                    </a>
                </div>
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

        <!-- Edit Form -->
        <form method="POST" action="" style="background:white;border-radius:var(--radius);padding:24px;border:1px solid var(--gray-200);">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <!-- Left Column -->
                <div>
                    <!-- Name -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Election Name <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="text" name="name" class="form-control" required
                               value="<?php echo htmlspecialchars($election['name']); ?>"
                               placeholder="e.g., 2027 Presidential Election"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    
                    <!-- Type -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Election Type <span style="color:#EF4444;">*</span>
                        </label>
                        <select name="type" class="form-control" required
                                style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                            <option value="">Select Type...</option>
                            <?php foreach ($election_types as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $election['type'] == $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Cycle -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Cycle
                        </label>
                        <input type="text" name="cycle" class="form-control"
                               value="<?php echo htmlspecialchars($election['cycle']); ?>"
                               placeholder="e.g., 2027, 2031"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    
                    <!-- Description -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Description
                        </label>
                        <textarea name="description" class="form-control" rows="3"
                                  placeholder="Election description..."
                                  style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;resize:vertical;transition:var(--transition);"><?php echo htmlspecialchars($election['description']); ?></textarea>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div>
                    <!-- Date and Time -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Election Date & Time <span style="color:#EF4444;">*</span>
                        </label>
                        <div style="margin-bottom:8px;">
                            <input type="date" name="election_date" class="form-control" required
                                   value="<?php echo htmlspecialchars($election['election_date']); ?>"
                                   style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                            <div>
                                <label style="display:block;font-size:0.65rem;color:var(--gray-400);">Start Time</label>
                                <input type="time" name="start_time" class="form-control"
                                       value="<?php echo htmlspecialchars($election['start_time']); ?>"
                                       style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;transition:var(--transition);">
                            </div>
                            <div>
                                <label style="display:block;font-size:0.65rem;color:var(--gray-400);">End Time</label>
                                <input type="time" name="end_time" class="form-control"
                                       value="<?php echo htmlspecialchars($election['end_time']); ?>"
                                       style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;transition:var(--transition);">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Status
                        </label>
                        <select name="status" class="form-control"
                                style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                            <?php foreach ($status_options as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $election['status'] == $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Locations -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Locations <span style="color:#EF4444;">*</span>
                        </label>
                        <p style="font-size:0.7rem;color:var(--gray-400);margin-bottom:8px;">
                            Hold Ctrl/Cmd to select multiple items
                        </p>
                        
                        <!-- States -->
                        <div style="margin-bottom:8px;">
                            <select name="states[]" multiple class="form-control"
                                    style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;min-height:60px;transition:var(--transition);">
                                <?php foreach ($states as $state): ?>
                                    <option value="<?php echo $state['id']; ?>"
                                        <?php echo in_array($state['id'], $selected_states) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($state['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div style="font-size:0.6rem;color:var(--gray-400);">States</div>
                        </div>
                        
                        <!-- LGAs -->
                        <div style="margin-bottom:8px;">
                            <select name="lgas[]" multiple class="form-control"
                                    style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;min-height:60px;transition:var(--transition);">
                                <?php foreach ($lgas as $lga): ?>
                                    <option value="<?php echo $lga['id']; ?>"
                                        <?php echo in_array($lga['id'], $selected_lgas) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lga['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div style="font-size:0.6rem;color:var(--gray-400);">LGAs</div>
                        </div>
                        
                        <!-- Wards -->
                        <div style="margin-bottom:8px;">
                            <select name="wards[]" multiple class="form-control"
                                    style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;min-height:60px;transition:var(--transition);">
                                <?php foreach ($wards as $ward): ?>
                                    <option value="<?php echo $ward['id']; ?>"
                                        <?php echo in_array($ward['id'], $selected_wards) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ward['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div style="font-size:0.6rem;color:var(--gray-400);">Wards</div>
                        </div>
                        
                        <!-- Polling Units -->
                        <div>
                            <select name="pus[]" multiple class="form-control"
                                    style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;min-height:60px;transition:var(--transition);">
                                <?php foreach ($polling_units as $pu): ?>
                                    <option value="<?php echo $pu['id']; ?>"
                                        <?php echo in_array($pu['id'], $selected_pus) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($pu['name']); ?> (<?php echo htmlspecialchars($pu['code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div style="font-size:0.6rem;color:var(--gray-400);">Polling Units</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div style="display:flex;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-200);flex-wrap:wrap;">
                <button type="submit" class="btn-primary" style="padding:10px 32px;background:var(--primary);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-save"></i> Update Election
                </button>
                <button type="reset" class="btn-secondary" style="padding:10px 32px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-weight:500;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <a href="election-view.php?id=<?php echo $election_id; ?>" class="btn-secondary" style="padding:10px 32px;background:transparent;color:var(--gray-500);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-family:'Inter',sans-serif;font-weight:500;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>

        <!-- Danger Zone -->
        <?php if ($election['status'] === 'draft' || $election['status'] === 'cancelled'): ?>
        <div style="background:#FEF2F2;border-radius:var(--radius);padding:16px 20px;margin-top:20px;border:1px solid #FECACA;">
            <h4 style="font-size:0.85rem;font-weight:600;color:#991B1B;margin:0 0 8px;">
                <i class="fas fa-exclamation-triangle"></i> Danger Zone
            </h4>
            <p style="font-size:0.8rem;color:#991B1B;margin:0 0 12px;">
                Deleting this election will remove all associated data including results, progress, and reports.
            </p>
            <a href="election-delete.php?id=<?php echo $election_id; ?>" class="btn-danger" style="padding:8px 20px;background:#EF4444;color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;" onclick="return confirm('Are you sure you want to delete this election? This action cannot be undone!')">
                <i class="fas fa-trash"></i> Delete Election
            </a>
        </div>
        <?php endif; ?>
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

.btn-danger:hover {
    background: #DC2626;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

select[multiple] {
    min-height: 80px;
}

select[multiple]:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

@media (max-width: 768px) {
    div[style*="grid-template-columns:1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
    div[style*="grid-template-columns:1fr 1fr;gap:8px;"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script>
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