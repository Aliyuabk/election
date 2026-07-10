<?php
// ============================================================
// STATE COORDINATOR - EDIT BROADCAST
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
$broadcast_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($broadcast_id <= 0) {
    header('Location: broadcasts.php');
    exit();
}

// Get broadcast details
$broadcast = null;
try {
    $stmt = $db->prepare("
        SELECT * FROM broadcasts 
        WHERE id = ? AND tenant_id = ? AND status IN ('draft', 'scheduled')
    ");
    $stmt->execute([$broadcast_id, $tenant_id]);
    $broadcast = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching broadcast: " . $e->getMessage());
}

if (!$broadcast) {
    header('Location: broadcasts.php');
    exit();
}

// Get LGAs for target selection
$lgas = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$state_id]);
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $message_content = trim($_POST['message'] ?? '');
    $target_audience = $_POST['target_audience'] ?? 'all';
    $target_ids = isset($_POST['target_ids']) ? array_map('intval', $_POST['target_ids']) : [];
    $send_via = isset($_POST['send_via']) ? $_POST['send_via'] : ['email'];
    
    if (empty($title) || empty($message_content)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $send_via_json = json_encode($send_via);
            $target_ids_json = !empty($target_ids) ? json_encode($target_ids) : null;
            
            $stmt = $db->prepare("
                UPDATE broadcasts 
                SET title = ?, message = ?, target_audience = ?, 
                    target_ids_json = ?, send_via = ?
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([
                $title,
                $message_content,
                $target_audience,
                $target_ids_json,
                $send_via_json,
                $broadcast_id,
                $tenant_id
            ]);
            
            logActivity($user_id, 'broadcast_updated', 
                "Updated broadcast: $title (ID: $broadcast_id)",
                'broadcasts', $broadcast_id
            );
            
            $message = "Broadcast updated successfully!";
            
            // Refresh broadcast data
            $stmt = $db->prepare("SELECT * FROM broadcasts WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$broadcast_id, $tenant_id]);
            $broadcast = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = 'Failed to update broadcast: ' . $e->getMessage();
        }
    }
}

$page_title = 'Edit Broadcast';
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
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    transition: var(--transition);
    background: white;
}

.form-group input[type="text"]:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.form-group textarea {
    resize: vertical;
    min-height: 120px;
}

.form-group .help-text {
    font-size: 0.65rem;
    color: var(--gray-400);
    margin-top: 4px;
}

.form-group .char-count {
    font-size: 0.65rem;
    color: var(--gray-400);
    text-align: right;
    margin-top: 2px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.checkbox-group {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 8px;
}

.checkbox-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 400;
    font-size: 0.8rem;
    color: var(--gray-700);
    cursor: pointer;
}

.checkbox-group input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: var(--primary);
}

.target-options {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 8px;
}

.target-options label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 400;
    font-size: 0.8rem;
    color: var(--gray-700);
    cursor: pointer;
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    transition: var(--transition);
}

.target-options label:hover {
    background: var(--gray-50);
}

.target-options input[type="radio"] {
    width: 16px;
    height: 16px;
    accent-color: var(--primary);
}

.target-options label.selected {
    border-color: var(--primary);
    background: rgba(var(--primary-rgb), 0.05);
}

.target-ids-section {
    margin-top: 10px;
    padding: 12px 16px;
    background: var(--gray-50);
    border-radius: 8px;
    display: none;
}

.target-ids-section.active {
    display: block;
}

.target-ids-section select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    font-family: 'Inter', sans-serif;
    background: white;
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

.btn-group .btn-save {
    background: var(--primary);
    color: white;
}

.btn-group .btn-save:hover {
    background: var(--primary-dark);
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

.status-badge.draft { background: #F3F4F6; color: #6B7280; }
.status-badge.draft .dot { background: #9CA3AF; }
.status-badge.scheduled { background: #FFFBEB; color: #92400E; }
.status-badge.scheduled .dot { background: #F59E0B; }

@media (max-width: 768px) {
    .form-card {
        padding: 16px 18px;
    }
    .form-row {
        grid-template-columns: 1fr;
    }
    .checkbox-group {
        grid-template-columns: 1fr 1fr;
    }
    .target-options {
        grid-template-columns: 1fr 1fr;
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
        <div class="form-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-edit"></i> Edit Broadcast</h1>
                    <p class="subtitle">
                        <i class="fas fa-bullhorn"></i> 
                        <?php echo htmlspecialchars($broadcast['title']); ?>
                        <span class="status-badge <?php echo $broadcast['status']; ?>" style="margin-left:8px;">
                            <span class="dot"></span>
                            <?php echo ucfirst($broadcast['status']); ?>
                        </span>
                    </p>
                </div>
                <div class="actions">
                    <a href="broadcasts.php" class="btn-secondary-sm">
                        <i class="fas fa-arrow-left"></i> Back to Broadcasts
                    </a>
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

            <form method="POST" action="">
                <!-- Basic Information -->
                <div class="form-card">
                    <div class="card-title"><i class="fas fa-info-circle"></i> Broadcast Details</div>
                    
                    <div class="form-group">
                        <label>Title <span class="required">*</span></label>
                        <input type="text" name="title" required placeholder="Enter broadcast title..." value="<?php echo htmlspecialchars($broadcast['title']); ?>" />
                    </div>

                    <div class="form-group">
                        <label>Message <span class="required">*</span></label>
                        <textarea name="message" id="messageInput" required placeholder="Type your message here..."><?php echo htmlspecialchars($broadcast['message']); ?></textarea>
                        <div class="char-count"><span id="charCount"><?php echo strlen($broadcast['message']); ?></span> characters</div>
                    </div>
                </div>

                <!-- Target Audience -->
                <div class="form-card">
                    <div class="card-title"><i class="fas fa-users"></i> Target Audience</div>
                    
                    <div class="form-group">
                        <label>Select Audience <span class="required">*</span></label>
                        <div class="target-options" id="targetOptions">
                            <label <?php echo $broadcast['target_audience'] === 'all' ? 'class="selected"' : ''; ?>>
                                <input type="radio" name="target_audience" value="all" <?php echo $broadcast['target_audience'] === 'all' ? 'checked' : ''; ?> onchange="toggleTargetIds()" />
                                All Users
                            </label>
                            <label <?php echo $broadcast['target_audience'] === 'state' ? 'class="selected"' : ''; ?>>
                                <input type="radio" name="target_audience" value="state" <?php echo $broadcast['target_audience'] === 'state' ? 'checked' : ''; ?> onchange="toggleTargetIds()" />
                                State Users
                            </label>
                            <label <?php echo $broadcast['target_audience'] === 'lga' ? 'class="selected"' : ''; ?>>
                                <input type="radio" name="target_audience" value="lga" <?php echo $broadcast['target_audience'] === 'lga' ? 'checked' : ''; ?> onchange="toggleTargetIds()" />
                                LGA Specific
                            </label>
                            <label <?php echo $broadcast['target_audience'] === 'role_specific' ? 'class="selected"' : ''; ?>>
                                <input type="radio" name="target_audience" value="role_specific" <?php echo $broadcast['target_audience'] === 'role_specific' ? 'checked' : ''; ?> onchange="toggleTargetIds()" />
                                Role Specific
                            </label>
                        </div>
                    </div>

                    <div class="target-ids-section <?php echo in_array($broadcast['target_audience'], ['lga', 'role_specific']) ? 'active' : ''; ?>" id="targetIdsSection">
                        <div class="form-group" id="lgaTarget" style="<?php echo $broadcast['target_audience'] === 'lga' ? 'display:block;' : 'display:none;'; ?>">
                            <label>Select LGAs</label>
                            <select name="target_ids[]" multiple>
                                <?php 
                                    $selected_ids = json_decode($broadcast['target_ids_json'] ?? '[]', true) ?: [];
                                    foreach ($lgas as $lga): 
                                ?>
                                    <option value="<?php echo $lga['id']; ?>" <?php echo in_array($lga['id'], $selected_ids) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lga['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="help-text">Hold Ctrl/Cmd to select multiple LGAs</div>
                        </div>
                        <div class="form-group" id="roleTarget" style="<?php echo $broadcast['target_audience'] === 'role_specific' ? 'display:block;' : 'display:none;'; ?>">
                            <label>Select Roles</label>
                            <select name="target_ids[]" multiple>
                                <?php 
                                    $role_options = ['lga' => 'LGA Coordinators', 'ward' => 'Ward Coordinators', 'pu_agent' => 'PU Agents'];
                                    foreach ($role_options as $key => $label): 
                                ?>
                                    <option value="<?php echo $key; ?>" <?php echo in_array($key, $selected_ids) ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="help-text">Hold Ctrl/Cmd to select multiple roles</div>
                        </div>
                    </div>
                </div>

                <!-- Delivery Channels -->
                <div class="form-card">
                    <div class="card-title"><i class="fas fa-share-alt"></i> Delivery Channels</div>
                    
                    <div class="form-group">
                        <label>Send Via <span class="required">*</span></label>
                        <div class="checkbox-group">
                            <?php 
                                $send_via = json_decode($broadcast['send_via'], true) ?: ['email'];
                            ?>
                            <label>
                                <input type="checkbox" name="send_via[]" value="email" <?php echo in_array('email', $send_via) ? 'checked' : ''; ?> />
                                <i class="fas fa-envelope"></i> Email
                            </label>
                            <label>
                                <input type="checkbox" name="send_via[]" value="in_app" <?php echo in_array('in_app', $send_via) ? 'checked' : ''; ?> />
                                <i class="fas fa-bell"></i> In-App
                            </label>
                            <label>
                                <input type="checkbox" name="send_via[]" value="sms" <?php echo in_array('sms', $send_via) ? 'checked' : ''; ?> />
                                <i class="fas fa-sms"></i> SMS
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-card">
                    <div class="btn-group">
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Update Broadcast
                        </button>
                        <a href="broadcasts.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
// Character counter
document.getElementById('messageInput')?.addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
});

// Toggle target IDs section
function toggleTargetIds() {
    var selected = document.querySelector('input[name="target_audience"]:checked');
    var section = document.getElementById('targetIdsSection');
    var lgaTarget = document.getElementById('lgaTarget');
    var roleTarget = document.getElementById('roleTarget');
    
    if (selected) {
        if (selected.value === 'lga' || selected.value === 'role_specific') {
            section.classList.add('active');
            if (selected.value === 'lga') {
                lgaTarget.style.display = 'block';
                roleTarget.style.display = 'none';
            } else {
                lgaTarget.style.display = 'none';
                roleTarget.style.display = 'block';
            }
        } else {
            section.classList.remove('active');
        }
    }
}

// Highlight selected target option
document.querySelectorAll('.target-options label').forEach(function(label) {
    label.addEventListener('click', function() {
        document.querySelectorAll('.target-options label').forEach(function(l) {
            l.classList.remove('selected');
        });
        this.classList.add('selected');
    });
});

// Same sidebar scripts as index.php
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
    toggleTargetIds();
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