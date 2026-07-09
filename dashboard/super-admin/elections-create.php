<?php
// ============================================================
// ELECTION CREATE - SUPER ADMINISTRATOR (FIXED)
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session and check login
SessionManager::start();

// Redirect if not logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Check role - only super_admin can access this page
if (SessionManager::get('role_level') !== 'super_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// ============================================================
// GET TENANT ID (optional pre-selection)
// ============================================================
$pre_selected_tenant = isset($_GET['tenant']) ? (int)$_GET['tenant'] : 0;

// ============================================================
// FETCH TENANT DETAILS (if pre-selected)
// ============================================================
$pre_selected_tenant_name = '';
if ($pre_selected_tenant > 0) {
    try {
        $stmt = $db->prepare("SELECT name FROM tenants WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$pre_selected_tenant]);
        $tenant = $stmt->fetch();
        if ($tenant) {
            $pre_selected_tenant_name = $tenant['name'];
        }
    } catch (Exception $e) {
        // Continue
    }
}

// ============================================================
// FETCH TENANTS FOR DROPDOWN
// ============================================================
$tenants = [];
try {
    $stmt = $db->query("SELECT id, name FROM tenants WHERE deleted_at IS NULL ORDER BY name");
    $tenants = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH STATES FOR DROPDOWN
// ============================================================
$states = [];
try {
    $stmt = $db->query("SELECT id, name FROM states WHERE is_active = 1 ORDER BY name");
    $states = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$error = '';
$success = '';
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'tenant_id' => (int)($_POST['tenant_id'] ?? 0),
        'name' => trim($_POST['name'] ?? ''),
        'type' => $_POST['type'] ?? 'presidential',
        'cycle' => trim($_POST['cycle'] ?? ''),
        'election_date' => $_POST['election_date'] ?? null,
        'start_time' => $_POST['start_time'] ?? null,
        'end_time' => $_POST['end_time'] ?? null,
        'description' => trim($_POST['description'] ?? ''),
        'status' => $_POST['status'] ?? 'draft',
        'states' => isset($_POST['states']) ? $_POST['states'] : [],
    ];

    $errors = [];
    
    // Validate required fields
    if (empty($form_data['tenant_id'])) {
        $errors[] = 'Please select a tenant.';
    }
    
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

    // If no errors, create election
    if (empty($errors)) {
        try {
            // Prepare JSON data for states
            $states_json = !empty($form_data['states']) ? json_encode($form_data['states']) : null;
            
            // Handle time fields - convert empty strings to NULL
            $start_time = !empty($form_data['start_time']) ? $form_data['start_time'] : null;
            $end_time = !empty($form_data['end_time']) ? $form_data['end_time'] : null;
            
            // Insert election
            $stmt = $db->prepare("
                INSERT INTO elections (
                    tenant_id, name, type, cycle, election_date,
                    start_time, end_time, description, status,
                    states_json, created_by, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, NOW()
                )
            ");
            
            $stmt->execute([
                $form_data['tenant_id'],
                $form_data['name'],
                $form_data['type'],
                $form_data['cycle'],
                $form_data['election_date'],
                $start_time,
                $end_time,
                $form_data['description'],
                $form_data['status'],
                $states_json,
                SessionManager::get('user_id')
            ]);
            
            $election_id = $db->lastInsertId();
            
            // Log activity
            logActivity(
                SessionManager::get('user_id'),
                'election_created',
                "Created election: {$form_data['name']} (ID: $election_id) for tenant ID: {$form_data['tenant_id']}"
            );
            
            $success = "Election created successfully!";
            $form_data = []; // Clear form
            
        } catch (PDOException $e) {
            $error = 'Database error creating election: ' . $e->getMessage();
            error_log("Election creation PDO Error: " . $e->getMessage());
        } catch (Exception $e) {
            $error = 'Error creating election: ' . $e->getMessage();
            error_log("Election creation Error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       ELECTION CREATE - PRO STYLES
       ============================================================ */
    
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
    }
    .page-header h2 small {
        font-size: 0.8rem;
        font-weight: 400;
        color: var(--gray-500);
        display: block;
        margin-top: 2px;
    }

    .btn-outline {
        padding: 8px 16px;
        background: transparent;
        color: var(--gray-600);
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.82rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-outline:hover {
        background: var(--gray-50);
        border-color: var(--gray-300);
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
        color: #8B5CF6;
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
        border-color: #8B5CF6;
        background: white;
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
    }
    .form-group input.error,
    .form-group select.error {
        border-color: var(--danger);
        background: #FEF2F2;
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
        color: #8B5CF6;
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
        background: #8B5CF6;
        color: white;
    }
    .form-actions .btn-primary:hover {
        background: #7C3AED;
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(139, 92, 246, 0.3);
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

    /* Tenant pre-select notice */
    .tenant-notice {
        background: #F5F3FF;
        border: 1px solid #EDE9FE;
        border-radius: 8px;
        padding: 10px 16px;
        color: #5B21B6;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 10px;
    }
    .tenant-notice i {
        font-size: 1rem;
    }

    /* Multi-select styling */
    select[multiple] {
        height: 120px;
        padding: 8px;
    }
    select[multiple] option {
        padding: 6px 10px;
        border-radius: 4px;
    }
    select[multiple] option:hover {
        background: #F5F3FF;
    }

    @media (max-width: 768px) {
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
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
    @media (max-width: 480px) {
        .form-container {
            padding: 16px;
        }
        .form-group input,
        .form-group select {
            padding: 8px 12px;
            font-size: 0.8rem;
        }
        .form-section-title {
            font-size: 0.8rem;
        }
        select[multiple] {
            height: 80px;
        }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-vote-yea" style="color:#8B5CF6;margin-right:8px;"></i> Create Election
                    <small>Create a new election for monitoring and result tracking</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="<?php echo $pre_selected_tenant > 0 ? 'tenants-elections.php?id=' . $pre_selected_tenant : 'elections.php'; ?>" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Error/Success Messages -->
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
                <i class="fas fa-calendar-plus"></i> Election Details
            </div>
            <div class="form-subtitle">
                Fill in the details below to create a new election.
                <?php if ($pre_selected_tenant > 0 && !empty($pre_selected_tenant_name)): ?>
                    <div class="tenant-notice">
                        <i class="fas fa-building"></i>
                        <span>Election will be created for: <strong><?php echo htmlspecialchars($pre_selected_tenant_name); ?></strong></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <form method="POST" action="" id="electionForm" novalidate>
                <div class="form-grid">
                    <!-- Basic Information -->
                    <div class="form-section-title">
                        <i class="fas fa-info-circle"></i> Basic Information
                    </div>
                    
                    <div class="form-group">
                        <label for="tenant_id">Tenant <span class="required">*</span></label>
                        <select name="tenant_id" id="tenant_id" required>
                            <option value="">Select Tenant</option>
                            <?php foreach ($tenants as $t): ?>
                                <option value="<?php echo $t['id']; ?>" <?php echo ($pre_selected_tenant == $t['id'] || ($form_data['tenant_id'] ?? 0) == $t['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="name">Election Name <span class="required">*</span></label>
                        <input type="text" name="name" id="name" placeholder="e.g., 2027 Presidential Election" value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="type">Election Type <span class="required">*</span></label>
                        <select name="type" id="type" required>
                            <option value="">Select Type</option>
                            <option value="presidential" <?php echo ($form_data['type'] ?? '') === 'presidential' ? 'selected' : ''; ?>>Presidential</option>
                            <option value="governorship" <?php echo ($form_data['type'] ?? '') === 'governorship' ? 'selected' : ''; ?>>Governorship</option>
                            <option value="senatorial" <?php echo ($form_data['type'] ?? '') === 'senatorial' ? 'selected' : ''; ?>>Senatorial</option>
                            <option value="house_of_reps" <?php echo ($form_data['type'] ?? '') === 'house_of_reps' ? 'selected' : ''; ?>>House of Representatives</option>
                            <option value="house_of_assembly" <?php echo ($form_data['type'] ?? '') === 'house_of_assembly' ? 'selected' : ''; ?>>House of Assembly</option>
                            <option value="lga_chairman" <?php echo ($form_data['type'] ?? '') === 'lga_chairman' ? 'selected' : ''; ?>>LGA Chairman</option>
                            <option value="councillorship" <?php echo ($form_data['type'] ?? '') === 'councillorship' ? 'selected' : ''; ?>>Councillorship</option>
                            <option value="party_primary" <?php echo ($form_data['type'] ?? '') === 'party_primary' ? 'selected' : ''; ?>>Party Primary</option>
                            <option value="internal_party" <?php echo ($form_data['type'] ?? '') === 'internal_party' ? 'selected' : ''; ?>>Internal Party</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="cycle">Election Cycle <span class="required">*</span></label>
                        <input type="text" name="cycle" id="cycle" placeholder="e.g., 2027" value="<?php echo htmlspecialchars($form_data['cycle'] ?? ''); ?>" required>
                        <div class="help-text">The election year or cycle (e.g., 2027, 2023-2027)</div>
                    </div>

                    <!-- Date & Time -->
                    <div class="form-section-title">
                        <i class="fas fa-calendar-alt"></i> Date &amp; Time
                    </div>
                    
                    <div class="form-group">
                        <label for="election_date">Election Date <span class="required">*</span></label>
                        <input type="date" name="election_date" id="election_date" value="<?php echo htmlspecialchars($form_data['election_date'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_time">Start Time</label>
                        <input type="time" name="start_time" id="start_time" value="<?php echo htmlspecialchars($form_data['start_time'] ?? ''); ?>">
                        <div class="help-text">When voting begins (optional)</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_time">End Time</label>
                        <input type="time" name="end_time" id="end_time" value="<?php echo htmlspecialchars($form_data['end_time'] ?? ''); ?>">
                        <div class="help-text">When voting ends (optional)</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="draft" <?php echo ($form_data['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="upcoming" <?php echo ($form_data['status'] ?? '') === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                            <option value="active" <?php echo ($form_data['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                        </select>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i> 
                            Draft - Not visible to users | Upcoming - Scheduled for future | Active - Currently ongoing
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="form-section-title">
                        <i class="fas fa-align-left"></i> Additional Information
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" placeholder="Enter election description, notes, or details..."><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                        <div class="help-text">Optional description or notes about the election.</div>
                    </div>

                    <!-- Jurisdiction -->
                    <div class="form-section-title">
                        <i class="fas fa-map-marked-alt"></i> Jurisdiction
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="states">States</label>
                        <select name="states[]" id="states" multiple>
                            <?php foreach ($states as $state): ?>
                                <option value="<?php echo $state['id']; ?>" <?php echo (isset($form_data['states']) && in_array($state['id'], $form_data['states'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($state['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i> 
                            Hold <kbd>Ctrl</kbd> (Windows) or <kbd>Cmd</kbd> (Mac) to select multiple states. Leave empty for all states.
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <div style="background:#F5F3FF;padding:14px 18px;border-radius:10px;color:#5B21B6;font-size:0.85rem;border:1px solid #EDE9FE;">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Note:</strong> You can configure specific LGAs, Wards, and Polling Units after creating the election. 
                            <span style="display:block;margin-top:4px;font-size:0.8rem;color:#7C3AED;">
                                <i class="fas fa-arrow-right"></i> Go to the election details page to add locations.
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check-circle"></i> Create Election
                    </button>
                    <a href="<?php echo $pre_selected_tenant > 0 ? 'tenants-elections.php?id=' . $pre_selected_tenant : 'elections.php'; ?>" class="btn btn-secondary">
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
    var tenant = document.getElementById('tenant_id');
    var name = document.getElementById('name');
    var type = document.getElementById('type');
    var cycle = document.getElementById('cycle');
    var electionDate = document.getElementById('election_date');
    var isValid = true;
    
    // Remove previous error states
    document.querySelectorAll('.error').forEach(function(el) {
        el.classList.remove('error');
    });
    
    // Validate tenant
    if (!tenant.value) {
        tenant.classList.add('error');
        isValid = false;
    }
    
    // Validate name
    if (!name.value.trim()) {
        name.classList.add('error');
        isValid = false;
    }
    
    // Validate type
    if (!type.value) {
        type.classList.add('error');
        isValid = false;
    }
    
    // Validate cycle
    if (!cycle.value.trim()) {
        cycle.classList.add('error');
        isValid = false;
    }
    
    // Validate election date
    if (!electionDate.value) {
        electionDate.classList.add('error');
        isValid = false;
    }
    
    if (!isValid) {
        e.preventDefault();
        // Scroll to first error
        var firstError = document.querySelector('.error');
        if (firstError) {
            firstError.focus();
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
});

// ============================================================
// SEARCH
// ============================================================
var searchInput = document.getElementById('searchInput');
var searchResults = document.getElementById('searchResults');
var searchTimeout;

if (searchInput) {
    searchInput.addEventListener('input', function() {
        var query = this.value.trim();
        clearTimeout(searchTimeout);
        if (query.length < 2) {
            if (searchResults) searchResults.classList.remove('active');
            return;
        }
        searchTimeout = setTimeout(function() {
            fetch('search.php?q=' + encodeURIComponent(query))
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (searchResults) {
                        searchResults.innerHTML = '';
                        if (data && data.length > 0) {
                            data.forEach(function(item) {
                                var div = document.createElement('a');
                                div.className = 'result-item';
                                div.href = item.url || '#';
                                div.innerHTML = '<i class="fas ' + (item.icon || 'fa-file') + '"></i><span class="text-truncate">' + (item.label || item.name || '') + '</span><span class="result-type">' + ((item.type || '').charAt(0).toUpperCase() + (item.type || '').slice(1)) + '</span>';
                                searchResults.appendChild(div);
                            });
                            searchResults.classList.add('active');
                        } else {
                            searchResults.innerHTML = '<div style="padding:12px;text-align:center;color:var(--gray-500);font-size:0.8rem;"><i class="fas fa-search" style="display:block;font-size:1.2rem;margin-bottom:4px;"></i>No results found</div>';
                            searchResults.classList.add('active');
                        }
                    }
                })
                .catch(function() {});
        }, 300);
    });

    document.addEventListener('click', function(e) {
        var wrapper = document.querySelector('.search-wrapper');
        if (wrapper && !wrapper.contains(e.target) && searchResults) {
            searchResults.classList.remove('active');
        }
    });
}
</script>
</body>
</html>