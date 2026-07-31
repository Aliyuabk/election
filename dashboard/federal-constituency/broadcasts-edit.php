<?php
// ============================================================
// FEDERAL CONSTITUENCY COORDINATOR - EDIT BROADCAST
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

$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$constituency_id = SessionManager::get('federal_constituency_id');
$state_id = SessionManager::get('state_id');
$db = getDB();

// ============================================================
// GET BROADCAST ID
// ============================================================
$broadcast_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$broadcast_id) {
    header('Location: broadcasts.php');
    exit();
}

// ============================================================
// GET BROADCAST DATA
// ============================================================
$broadcast = null;
try {
    $stmt = $db->prepare("
        SELECT * FROM broadcasts 
        WHERE id = ? AND tenant_id = ? AND status = 'draft'
    ");
    $stmt->execute([$broadcast_id, $tenant_id]);
    $broadcast = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching broadcast: " . $e->getMessage());
}

if (!$broadcast) {
    header('Location: broadcasts.php');
    exit();
}

// ============================================================
// GET LGA IDs
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
// GET LGAS AND WARDS
// ============================================================
$lgas = [];
$wards = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE id IN ($lga_list) ORDER BY name ASC");
        $stmt->execute();
        $lgas = $stmt->fetchAll();
        
        $stmt = $db->prepare("
            SELECT w.id, w.name, l.name as lga_name 
            FROM wards w 
            JOIN lgas l ON w.lga_id = l.id 
            WHERE w.lga_id IN ($lga_list) 
            ORDER BY l.name ASC, w.name ASC
        ");
        $stmt->execute();
        $wards = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching LGAs/Wards: " . $e->getMessage());
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $target_audience = $_POST['target_audience'] ?? 'all';
    $priority = $_POST['priority'] ?? 'low';
    $send_via = isset($_POST['send_via']) ? $_POST['send_via'] : ['in_app'];
    $action = $_POST['action'] ?? 'update';
    
    if (empty($title)) {
        $error = 'Please enter a title.';
    } elseif (empty($message)) {
        $error = 'Please enter a message.';
    } else {
        try {
            $target_ids = [];
            if ($target_audience === 'lga') {
                $target_ids = $_POST['target_lgas'] ?? [];
            } elseif ($target_audience === 'ward') {
                $target_ids = $_POST['target_wards'] ?? [];
            }
            
            $stmt = $db->prepare("
                UPDATE broadcasts SET 
                    title = ?,
                    message = ?,
                    target_audience = ?,
                    target_ids_json = ?,
                    send_via = ?,
                    priority = ?
                WHERE id = ? AND tenant_id = ?
            ");
            
            $stmt->execute([
                $title,
                $message,
                $target_audience,
                json_encode($target_ids),
                json_encode($send_via),
                $priority,
                $broadcast_id,
                $tenant_id
            ]);
            
            logActivity($user_id, 'broadcast_updated', "Updated broadcast: $title (ID: $broadcast_id)", 'broadcasts', $broadcast_id);
            
            $success = 'Broadcast updated successfully!';
            
            // Refresh broadcast data
            $stmt = $db->prepare("SELECT * FROM broadcasts WHERE id = ?");
            $stmt->execute([$broadcast_id]);
            $broadcast = $stmt->fetch();
            
        } catch (Exception $e) {
            $error = 'Error updating broadcast: ' . $e->getMessage();
            error_log("Broadcast update error: " . $e->getMessage());
        }
    }
}

$page_title = 'Edit Broadcast';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.form-container {
    max-width: 900px;
    margin: 0 auto;
}
.form-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 30px;
}
.form-group {
    margin-bottom: 18px;
}
.form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.82rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.form-group label .required { color: #DC2626; }
.form-group .help-text {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 4px;
}
.form-group input,
.form-group select,
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
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}
.form-group textarea {
    min-height: 150px;
    resize: vertical;
}
.form-group .checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    padding-top: 4px;
}
.form-group .checkbox-group label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 400;
    font-size: 0.85rem;
    cursor: pointer;
}
.form-group .checkbox-group input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--gray-200);
    flex-wrap: wrap;
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
.btn-success {
    background: #10B981;
    color: white;
}
.btn-success:hover {
    background: #059669;
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
.alert-success {
    background: #ECFDF5;
    color: #065F46;
    border: 1px solid #A7F3D0;
}
.alert-error {
    background: #FEF2F2;
    color: #DC2626;
    border: 1px solid #FECACA;
}

.target-options {
    display: none;
    margin-top: 10px;
    padding: 14px;
    background: var(--gray-50);
    border-radius: 10px;
}
.target-options.active {
    display: block;
}
.target-options select {
    width: 100%;
    padding: 8px 12px;
    border: 1.5px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.85rem;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    .form-card {
        padding: 16px;
    }
    .form-actions {
        flex-direction: column;
    }
    .form-actions .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="form-container">
            <h2 style="font-size:1.3rem;font-weight:700;margin-bottom:4px;">
                <i class="fas fa-edit" style="color:var(--primary);"></i> Edit Broadcast
            </h2>
            <p style="color:var(--gray-500);font-size:0.9rem;margin-bottom:20px;">
                Update your broadcast message.
            </p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo htmlspecialchars($success); ?></div>
                </div>
            <?php endif; ?>

            <div class="form-card">
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Title <span class="required">*</span></label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($broadcast['title']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Message <span class="required">*</span></label>
                        <textarea name="message" required><?php echo htmlspecialchars($broadcast['message']); ?></textarea>
                        <div class="help-text">Supports plain text only.</div>
                    </div>

                    <div class="form-group">
                        <label>Target Audience <span class="required">*</span></label>
                        <select name="target_audience" id="target_audience" required>
                            <option value="all" <?php echo ($broadcast['target_audience'] === 'all') ? 'selected' : ''; ?>>All Coordinators</option>
                            <option value="lga" <?php echo ($broadcast['target_audience'] === 'lga') ? 'selected' : ''; ?>>LGA Coordinators</option>
                            <option value="ward" <?php echo ($broadcast['target_audience'] === 'ward') ? 'selected' : ''; ?>>Ward Coordinators</option>
                        </select>
                    </div>

                    <div class="target-options" id="lga_target">
                        <div class="form-group">
                            <label>Select LGAs</label>
                            <select name="target_lgas[]" multiple size="4" style="height:auto;">
                                <?php foreach ($lgas as $lga): ?>
                                    <option value="<?php echo $lga['id']; ?>" <?php 
                                        $target_ids = json_decode($broadcast['target_ids_json'] ?? '[]', true) ?: [];
                                        echo in_array($lga['id'], $target_ids) ? 'selected' : ''; 
                                    ?>>
                                        <?php echo htmlspecialchars($lga['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="help-text">Hold Ctrl/Cmd to select multiple LGAs</div>
                        </div>
                    </div>

                    <div class="target-options" id="ward_target">
                        <div class="form-group">
                            <label>Select Wards</label>
                            <select name="target_wards[]" multiple size="4" style="height:auto;">
                                <?php foreach ($wards as $ward): ?>
                                    <option value="<?php echo $ward['id']; ?>" <?php 
                                        $target_ids = json_decode($broadcast['target_ids_json'] ?? '[]', true) ?: [];
                                        echo in_array($ward['id'], $target_ids) ? 'selected' : ''; 
                                    ?>>
                                        <?php echo htmlspecialchars($ward['name']); ?> (<?php echo htmlspecialchars($ward['lga_name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="help-text">Hold Ctrl/Cmd to select multiple wards</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Priority</label>
                            <select name="priority">
                                <option value="low" <?php echo ($broadcast['priority'] === 'low') ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo ($broadcast['priority'] === 'medium') ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo ($broadcast['priority'] === 'high') ? 'selected' : ''; ?>>High</option>
                                <option value="emergency" <?php echo ($broadcast['priority'] === 'emergency') ? 'selected' : ''; ?>>Emergency</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Send Via</label>
                            <div class="checkbox-group">
                                <label>
                                    <input type="checkbox" name="send_via[]" value="in_app" <?php 
                                        $send_via = json_decode($broadcast['send_via'] ?? '["in_app"]', true) ?: ['in_app'];
                                        echo in_array('in_app', $send_via) ? 'checked' : ''; 
                                    ?>>
                                    In-App
                                </label>
                                <label>
                                    <input type="checkbox" name="send_via[]" value="email" <?php 
                                        echo in_array('email', $send_via) ? 'checked' : ''; 
                                    ?>>
                                    Email
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="action" value="update" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Broadcast
                        </button>
                        <a href="broadcasts.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
document.getElementById('target_audience').addEventListener('change', function() {
    var value = this.value;
    document.getElementById('lga_target').classList.toggle('active', value === 'lga');
    document.getElementById('ward_target').classList.toggle('active', value === 'ward');
});

document.addEventListener('DOMContentLoaded', function() {
    var event = new Event('change');
    document.getElementById('target_audience').dispatchEvent(event);
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