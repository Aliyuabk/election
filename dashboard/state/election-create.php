<?php
// ============================================================
// STATE COORDINATOR - ELECTION CREATE (FIXED)
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
$state_code = '';
try {
    if (!empty($state_id)) {
        $stmt = $db->prepare("SELECT name, code FROM states WHERE id = ?");
        $stmt->execute([$state_id]);
        $state = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($state) {
            $state_name = $state['name'] ?? 'Unknown State';
            $state_code = $state['code'] ?? '';
        }
    }
} catch (Exception $e) {
    error_log("Error fetching state: " . $e->getMessage());
}

// ============================================================
// ELECTION TYPES
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
            'name' => trim($_POST['name'] ?? ''),
            'type' => $_POST['type'] ?? '',
            'cycle' => trim($_POST['cycle'] ?? ''),
            'election_date' => $_POST['election_date'] ?? '',
            'start_time' => $_POST['start_time'] ?? null,
            'end_time' => $_POST['end_time'] ?? null,
            'status' => $_POST['status'] ?? 'draft',
            'description' => trim($_POST['description'] ?? ''),
        ];
        
        $errors = [];
        
        if (empty($form_data['name'])) {
            $errors[] = 'Election name is required.';
        }
        if (empty($form_data['type'])) {
            $errors[] = 'Election type is required.';
        }
        if (empty($form_data['cycle'])) {
            $errors[] = 'Election cycle is required.';
        }
        if (empty($form_data['election_date'])) {
            $errors[] = 'Election date is required.';
        }
        
        if (empty($errors)) {
            try {
                // IMPORTANT: Set states_json as JSON array with the state ID
                $states_json = json_encode([(int)$state_id]);
                
                // Also set default empty arrays for other jurisdiction fields
                $lgas_json = json_encode([]);
                $wards_json = json_encode([]);
                $pus_json = json_encode([]);
                
                $stmt = $db->prepare("
                    INSERT INTO elections (
                        tenant_id, name, type, cycle, election_date,
                        start_time, end_time, states_json, lgas_json, wards_json, pus_json,
                        status, description, created_by, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, NOW()
                    )
                ");
                
                $stmt->execute([
                    $tenant_id,
                    $form_data['name'],
                    $form_data['type'],
                    $form_data['cycle'],
                    $form_data['election_date'],
                    $form_data['start_time'] ?: null,
                    $form_data['end_time'] ?: null,
                    $states_json,
                    $lgas_json,
                    $wards_json,
                    $pus_json,
                    $form_data['status'],
                    $form_data['description'],
                    $user_id
                ]);
                
                $election_id = $db->lastInsertId();
                
                logActivity($user_id, 'election_created', "Created election: {$form_data['name']} (ID: $election_id) for tenant ID: $tenant_id in state: $state_name");
                
                $success = "Election created successfully!";
                
                // Clear form data
                $form_data = [];
                
                // Regenerate CSRF token
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $csrf_token = $_SESSION['csrf_token'];
                
            } catch (Exception $e) {
                $error = 'Error creating election: ' . $e->getMessage();
                error_log("Election creation error: " . $e->getMessage());
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
    color: var(--primary);
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
    min-height: 80px;
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
    color: var(--primary);
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
.form-actions .btn-primary {
    background: var(--primary);
    color: white;
}
.form-actions .btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25);
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

.tenant-notice {
    background: #EFF6FF;
    border: 1px solid #BFDBFE;
    border-radius: 8px;
    padding: 10px 16px;
    color: #1E40AF;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
}
.tenant-notice i {
    font-size: 1rem;
}
.tenant-notice strong {
    color: #1E3A8A;
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
                    <i class="fas fa-plus-circle" style="color:var(--primary);margin-right:8px;"></i>
                    Create Election
                    <small>Create a new election for <?php echo htmlspecialchars($state_name); ?></small>
                </h2>
            </div>
            <div>
                <a href="elections.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Elections
                </a>
            </div>
        </div>

        <!-- Notice -->
        <div class="tenant-notice">
            <i class="fas fa-info-circle"></i>
            <span>This election will be created for <strong><?php echo htmlspecialchars($state_name); ?></strong> State.</span>
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
                <i class="fas fa-vote-yea"></i> Election Information
            </div>
            <div class="form-subtitle">
                Fill in the details below to create a new election.
            </div>
            
            <form method="POST" action="" id="electionForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="form-grid">
                    <!-- Election Details -->
                    <div class="form-section-title">
                        <i class="fas fa-info-circle"></i> Election Details
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="name">Election Name <span class="required">*</span></label>
                        <input type="text" name="name" id="name" placeholder="e.g., 2027 Governorship Election" value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>" required>
                        <div class="help-text">A clear, descriptive name for the election.</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="type">Election Type <span class="required">*</span></label>
                        <select name="type" id="type" required>
                            <option value="">Select Type</option>
                            <?php foreach ($election_types as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($form_data['type'] ?? '') === $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="cycle">Election Cycle <span class="required">*</span></label>
                        <input type="text" name="cycle" id="cycle" placeholder="e.g., 2027" value="<?php echo htmlspecialchars($form_data['cycle'] ?? ''); ?>" required>
                        <div class="help-text">The year or cycle of the election.</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="election_date">Election Date <span class="required">*</span></label>
                        <input type="date" name="election_date" id="election_date" value="<?php echo htmlspecialchars($form_data['election_date'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="draft" <?php echo ($form_data['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="upcoming" <?php echo ($form_data['status'] ?? '') === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                            <option value="active" <?php echo ($form_data['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                        </select>
                        <div class="help-text">Draft: Not visible to users. Upcoming: Visible but not started. Active: Live.</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_time">Start Time</label>
                        <input type="time" name="start_time" id="start_time" value="<?php echo htmlspecialchars($form_data['start_time'] ?? ''); ?>">
                        <div class="help-text">When voting officially starts (optional).</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_time">End Time</label>
                        <input type="time" name="end_time" id="end_time" value="<?php echo htmlspecialchars($form_data['end_time'] ?? ''); ?>">
                        <div class="help-text">When voting officially ends (optional).</div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" placeholder="Brief description of the election..."><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                        <div class="help-text">Optional description of the election.</div>
                    </div>

                    <!-- State Information (Read-only) -->
                    <div class="form-section-title">
                        <i class="fas fa-map-marker-alt"></i> Jurisdiction
                    </div>
                    
                    <div class="form-group full-width">
                        <div style="background:var(--gray-50);padding:12px 16px;border-radius:8px;border:1px solid var(--gray-200);">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <i class="fas fa-flag" style="color:var(--primary);"></i>
                                <span style="font-weight:600;">State:</span>
                                <span><?php echo htmlspecialchars($state_name); ?></span>
                                <?php if (!empty($state_code)): ?>
                                    <span style="color:var(--gray-400);font-size:0.8rem;">(<?php echo htmlspecialchars($state_code); ?>)</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:0.7rem;color:var(--gray-400);margin-top:4px;">
                                <i class="fas fa-info-circle"></i> This election will be available in <?php echo htmlspecialchars($state_name); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check-circle"></i> Create Election
                    </button>
                    <a href="elections.php" class="btn btn-secondary">
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
// FORM VALIDATION
// ============================================================
document.getElementById('electionForm').addEventListener('submit', function(e) {
    var name = document.getElementById('name');
    var type = document.getElementById('type');
    var cycle = document.getElementById('cycle');
    var date = document.getElementById('election_date');
    var isValid = true;
    
    document.querySelectorAll('.error').forEach(function(el) {
        el.classList.remove('error');
    });
    
    if (!name.value.trim()) {
        name.classList.add('error');
        isValid = false;
    }
    if (!type.value) {
        type.classList.add('error');
        isValid = false;
    }
    if (!cycle.value.trim()) {
        cycle.classList.add('error');
        isValid = false;
    }
    if (!date.value) {
        date.classList.add('error');
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