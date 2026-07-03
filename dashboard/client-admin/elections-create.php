<?php
// ============================================================
// ELECTION CREATE - CLIENT ADMIN
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

// Check role - only client_admin can access this page
if (SessionManager::get('role_level') !== 'client_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// Get user info
$user_id = SessionManager::get('user_id');
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');
$tenant_id = SessionManager::get('tenant_id');

// ============================================================
// FETCH TENANT DETAILS
// ============================================================
$tenant = null;
try {
    $stmt = $db->prepare("SELECT id, name FROM tenants WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$tenant_id]);
    $tenant = $stmt->fetch();
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
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_election':
                $name = trim($_POST['name'] ?? '');
                $type = $_POST['type'] ?? 'presidential';
                $cycle = trim($_POST['cycle'] ?? '');
                $election_date = $_POST['election_date'] ?? null;
                $start_time = $_POST['start_time'] ?? null;
                $end_time = $_POST['end_time'] ?? null;
                $status = $_POST['status'] ?? 'draft';
                $description = trim($_POST['description'] ?? '');
                $states_json = isset($_POST['states']) ? json_encode($_POST['states']) : null;
                
                if (empty($name)) {
                    throw new Exception('Election name is required.');
                }
                if (empty($type)) {
                    throw new Exception('Election type is required.');
                }
                if (empty($cycle)) {
                    throw new Exception('Election cycle is required.');
                }
                if (empty($election_date)) {
                    throw new Exception('Election date is required.');
                }
                
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
                    $tenant_id,
                    $name,
                    $type,
                    $cycle,
                    $election_date,
                    $start_time,
                    $end_time,
                    $description,
                    $status,
                    $states_json,
                    $user_id
                ]);
                
                $election_id = $db->lastInsertId();
                
                logActivity($user_id, 'election_created', "Created election: $name (ID: $election_id)");
                
                $success = "Election created successfully!";
                $form_data = [];
                
                // Redirect to election view
                header("Location: elections-view.php?id=$election_id&created=1");
                exit();
                break;
                
            case 'upload_document':
                if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Please select a document to upload.');
                }
                
                $file = $_FILES['document'];
                $doc_type = $_POST['doc_type'] ?? 'guidelines';
                $election_id = (int)($_POST['election_id'] ?? 0);
                
                if ($election_id <= 0) {
                    throw new Exception('Invalid election ID.');
                }
                
                $upload_dir = '../../uploads/elections/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
                
                if (!in_array($ext, $allowed)) {
                    throw new Exception('Invalid file type. Allowed: PDF, DOC, DOCX, XLS, XLSX, TXT');
                }
                
                $filename = 'election_' . $election_id . '_' . $doc_type . '_' . time() . '.' . $ext;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Store document reference in election settings or a separate table
                    // For now, just log the upload
                    logActivity($user_id, 'election_document_uploaded', "Uploaded document for election ID: $election_id");
                    $success = "Document uploaded successfully!";
                } else {
                    throw new Exception('Failed to upload document.');
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       ELECTION CREATE - CLIENT ADMIN STYLES
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
    .btn-primary {
        padding: 8px 18px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25);
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
        border: 1.5px solid var(--gray-200);
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
    
    select[multiple] {
        height: 120px;
        padding: 8px;
    }
    select[multiple] option {
        padding: 6px 10px;
        border-radius: 4px;
    }
    select[multiple] option:hover {
        background: #EFF6FF;
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
    
    .alert {
        padding: 14px 18px;
        border-radius: 10px;
        font-size: 0.85rem;
        margin-bottom: 16px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        border: 1px solid transparent;
    }
    .alert i {
        margin-top: 2px;
        font-size: 1.1rem;
    }
    .alert-success {
        background: #ECFDF5;
        color: #065F46;
        border-color: #A7F3D0;
    }
    .alert-error {
        background: #FEF2F2;
        color: #DC2626;
        border-color: #FECACA;
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
                    <i class="fas fa-plus-circle" style="color:var(--primary);margin-right:8px;"></i> Create Election
                    <small>Create a new election for your organization</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="elections-templates.php" class="btn-outline">
                    <i class="fas fa-copy"></i> Use Template
                </a>
                <a href="elections.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Elections
                </a>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $success; ?></div>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="form-container">
            <div class="form-title">
                <i class="fas fa-vote-yea" style="color:var(--primary);"></i> Election Details
            </div>
            <div class="form-subtitle">
                Fill in the details below to create a new election for <?php echo htmlspecialchars($tenant['name'] ?? 'your organization'); ?>.
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_election">
                
                <div class="form-grid">
                    <!-- Basic Information -->
                    <div class="form-section-title">
                        <i class="fas fa-info-circle"></i> Basic Information
                    </div>
                    
                    <div class="form-group">
                        <label>Election Name <span class="required">*</span></label>
                        <input type="text" name="name" placeholder="e.g., 2027 Presidential Election" value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Election Type <span class="required">*</span></label>
                        <select name="type" required>
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
                        <label>Election Cycle <span class="required">*</span></label>
                        <input type="text" name="cycle" placeholder="e.g., 2027" value="<?php echo htmlspecialchars($form_data['cycle'] ?? ''); ?>" required>
                        <div class="help-text">The election year or cycle (e.g., 2027, 2023-2027)</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="draft" <?php echo ($form_data['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="upcoming" <?php echo ($form_data['status'] ?? '') === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                            <option value="active" <?php echo ($form_data['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                        </select>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i> 
                            Draft - Not visible to users | Upcoming - Scheduled for future | Active - Currently ongoing
                        </div>
                    </div>

                    <!-- Date & Time -->
                    <div class="form-section-title">
                        <i class="fas fa-calendar-alt"></i> Date &amp; Time
                    </div>
                    
                    <div class="form-group">
                        <label>Election Date <span class="required">*</span></label>
                        <input type="date" name="election_date" value="<?php echo htmlspecialchars($form_data['election_date'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="start_time" value="<?php echo htmlspecialchars($form_data['start_time'] ?? ''); ?>">
                        <div class="help-text">When voting begins (optional)</div>
                    </div>
                    
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="end_time" value="<?php echo htmlspecialchars($form_data['end_time'] ?? ''); ?>">
                        <div class="help-text">When voting ends (optional)</div>
                    </div>

                    <!-- Additional Information -->
                    <div class="form-section-title">
                        <i class="fas fa-align-left"></i> Additional Information
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="description" placeholder="Enter election description, notes, or details..."><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                        <div class="help-text">Optional description or notes about the election.</div>
                    </div>

                    <!-- Jurisdiction -->
                    <div class="form-section-title">
                        <i class="fas fa-map-marked-alt"></i> Jurisdiction
                    </div>
                    
                    <div class="form-group full-width">
                        <label>States</label>
                        <select name="states[]" multiple required>
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
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
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