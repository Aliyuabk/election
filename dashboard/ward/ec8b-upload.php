<?php
// ============================================================
// WARD COORDINATOR - UPLOAD EC8B FORM
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Ward Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$ward_id = SessionManager::get('ward_id');

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
$ec8b_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ec8b_id <= 0) {
    header('Location: ec8b-history.php');
    exit();
}

// Get EC8B details
$ec8b = null;
try {
    $stmt = $db->prepare("
        SELECT r.*, e.name as election_name, w.name as ward_name
        FROM results_ec8b r
        JOIN elections e ON r.election_id = e.id
        JOIN wards w ON r.ward_id = w.id
        WHERE r.id = ? AND r.tenant_id = ? AND r.ward_id = ?
    ");
    $stmt->execute([$ec8b_id, $tenant_id, $ward_id]);
    $ec8b = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching EC8B: " . $e->getMessage());
}

if (!$ec8b) {
    header('Location: ec8b-history.php');
    exit();
}

$message = '';
$error = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['ec8b_form']) && $_FILES['ec8b_form']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['ec8b_form'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        $max_size = 10 * 1024 * 1024; // 10MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $error = 'Invalid file type. Please upload JPEG, PNG, GIF, WebP, or PDF.';
        } elseif ($file['size'] > $max_size) {
            $error = 'File too large. Maximum size is 10MB.';
        } else {
            try {
                $upload_dir = '../../uploads/ec8b/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'ec8b_' . $ec8b_id . '_' . time() . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $db_path = '/election/uploads/ec8b/' . $filename;
                    
                    $stmt = $db->prepare("UPDATE results_ec8b SET form_photo_url = ? WHERE id = ? AND tenant_id = ?");
                    $stmt->execute([$db_path, $ec8b_id, $tenant_id]);
                    
                    logActivity($user_id, 'ec8b_form_uploaded', 
                        "Uploaded EC8B form for ID: $ec8b_id",
                        'results_ec8b', $ec8b_id
                    );
                    
                    $message = "EC8B form uploaded successfully!";
                    
                    // Refresh data
                    $stmt = $db->prepare("SELECT * FROM results_ec8b WHERE id = ? AND tenant_id = ?");
                    $stmt->execute([$ec8b_id, $tenant_id]);
                    $ec8b = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = 'Failed to upload file.';
                }
            } catch (Exception $e) {
                $error = 'Failed to upload: ' . $e->getMessage();
                error_log("EC8B upload error: " . $e->getMessage());
            }
        }
    } else {
        $error = 'Please select a file to upload.';
    }
}

$page_title = 'Upload EC8B Form';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.upload-container {
    max-width: 600px;
    margin: 0 auto;
}

.upload-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
}

.upload-card .card-title {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.upload-card .card-title i {
    color: var(--primary);
    margin-right: 6px;
}

.ec8b-info {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 16px;
}

.ec8b-info .label {
    font-size: 0.6rem;
    color: var(--gray-500);
    display: block;
}

.ec8b-info .value {
    font-weight: 500;
    color: var(--gray-800);
    font-size: 0.85rem;
}

.current-file {
    background: #F0F9FF;
    border: 1px solid #BAE6FD;
    border-radius: 8px;
    padding: 10px 14px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.current-file i {
    font-size: 1.5rem;
    color: #3B82F6;
}

.current-file .file-info .name {
    font-weight: 500;
    font-size: 0.85rem;
}

.current-file .file-info .size {
    font-size: 0.65rem;
    color: var(--gray-400);
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

.file-upload-area {
    border: 2px dashed var(--gray-300);
    border-radius: 10px;
    padding: 30px;
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
    background: var(--gray-50);
}

.file-upload-area:hover {
    border-color: var(--primary);
    background: #EFF6FF;
}

.file-upload-area i {
    font-size: 2.5rem;
    color: var(--gray-400);
    display: block;
    margin-bottom: 8px;
}

.file-upload-area p {
    font-size: 0.9rem;
    color: var(--gray-600);
    margin: 0;
}

.file-upload-area .file-types {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 4px;
}

.file-upload-area input[type="file"] {
    display: none;
}

.file-preview {
    display: none;
    margin-top: 12px;
    padding: 10px 14px;
    background: var(--gray-50);
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.file-preview.show {
    display: flex;
}

.file-preview i {
    font-size: 1.5rem;
    color: #10B981;
}

.file-preview .file-name {
    font-weight: 500;
    font-size: 0.85rem;
}

.file-preview .file-size {
    font-size: 0.65rem;
    color: var(--gray-400);
}

.alert {
    padding: 10px 14px;
    border-radius: 8px;
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
    padding: 10px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-group .btn-upload {
    background: var(--primary);
    color: white;
}

.btn-group .btn-upload:hover {
    background: var(--primary-dark);
}

.btn-group .btn-upload:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-group .btn-cancel {
    background: var(--gray-100);
    color: var(--gray-700);
    text-decoration: none;
    padding: 10px 24px;
    border-radius: 8px;
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

@media (max-width: 768px) {
    .upload-card {
        padding: 16px 18px;
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
        <div class="upload-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-file-upload"></i> Upload EC8B Form</h1>
                    <p class="subtitle">
                        <i class="fas fa-file-alt"></i> 
                        EC8B #<?php echo $ec8b_id; ?> - <?php echo htmlspecialchars($ec8b['election_name']); ?>
                    </p>
                </div>
                <div class="actions">
                    <a href="ec8b-history.php" class="btn-secondary-sm">
                        <i class="fas fa-history"></i> Back to History
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

            <div class="upload-card">
                <div class="card-title"><i class="fas fa-info-circle"></i> EC8B Information</div>
                
                <div class="ec8b-info">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                        <div>
                            <span class="label">Election</span>
                            <span class="value"><?php echo htmlspecialchars($ec8b['election_name']); ?></span>
                        </div>
                        <div>
                            <span class="label">Ward</span>
                            <span class="value"><?php echo htmlspecialchars($ec8b['ward_name']); ?></span>
                        </div>
                        <div>
                            <span class="label">Valid Votes</span>
                            <span class="value"><?php echo number_format($ec8b['valid_votes']); ?></span>
                        </div>
                        <div>
                            <span class="label">Status</span>
                            <span class="value">
                                <span class="status-badge <?php echo $ec8b['status']; ?>">
                                    <span class="dot"></span>
                                    <?php echo ucfirst($ec8b['status']); ?>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>

                <?php if (!empty($ec8b['form_photo_url'])): ?>
                    <div class="current-file">
                        <i class="fas fa-file-image"></i>
                        <div class="file-info">
                            <div class="name">Current form uploaded</div>
                            <div class="size">
                                <a href="<?php echo htmlspecialchars($ec8b['form_photo_url']); ?>" target="_blank" style="color:var(--primary);">
                                    <i class="fas fa-external-link-alt"></i> View current upload
                                </a>
                            </div>
                        </div>
                        <span style="margin-left:auto;font-size:0.6rem;color:#10B981;">
                            <i class="fas fa-check-circle"></i> Uploaded
                        </span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>EC8B Form <span class="required">*</span></label>
                        <div class="file-upload-area" onclick="document.getElementById('ec8bFile').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to upload or drag &amp; drop</p>
                            <div class="file-types">Supported: JPEG, PNG, GIF, WebP, PDF (Max 10MB)</div>
                            <input type="file" name="ec8b_form" id="ec8bFile" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" required />
                        </div>
                        <div class="file-preview" id="filePreview">
                            <i class="fas fa-file"></i>
                            <div>
                                <div class="file-name" id="fileName">file.jpg</div>
                                <div class="file-size" id="fileSize">0 KB</div>
                            </div>
                            <button type="button" onclick="clearFile()" style="margin-left:auto;background:none;border:none;color:#EF4444;cursor:pointer;font-size:1rem;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <div class="btn-group">
                        <a href="ec8b-history.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn-upload" id="uploadBtn">
                            <i class="fas fa-upload"></i> Upload EC8B Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
document.getElementById('ec8bFile').addEventListener('change', function() {
    var preview = document.getElementById('filePreview');
    var fileName = document.getElementById('fileName');
    var fileSize = document.getElementById('fileSize');
    var uploadBtn = document.getElementById('uploadBtn');
    
    if (this.files && this.files[0]) {
        var file = this.files[0];
        fileName.textContent = file.name;
        fileSize.textContent = (file.size / 1024).toFixed(1) + ' KB';
        preview.classList.add('show');
        uploadBtn.disabled = false;
    } else {
        clearFile();
    }
});

function clearFile() {
    var preview = document.getElementById('filePreview');
    var fileInput = document.getElementById('ec8bFile');
    preview.classList.remove('show');
    fileInput.value = '';
    document.getElementById('uploadBtn').disabled = true;
}

// Same sidebar scripts as index.php
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