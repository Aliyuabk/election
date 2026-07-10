<?php
// ============================================================
// WARD COORDINATOR - ATTACH IMAGE TO EC8B
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

// Get existing attachments
$attachments = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM ec8b_attachments 
        WHERE ec8b_id = ? AND tenant_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$ec8b_id, $tenant_id]);
    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist, create it
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ec8b_attachments` (
                `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id` bigint(20) UNSIGNED NOT NULL,
                `ec8b_id` bigint(20) UNSIGNED NOT NULL,
                `user_id` bigint(20) UNSIGNED NOT NULL,
                `file_name` varchar(255) NOT NULL,
                `file_url` varchar(500) NOT NULL,
                `file_size` bigint(20) UNSIGNED NOT NULL,
                `file_type` varchar(100) NOT NULL,
                `description` text DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `idx_attachments_ec8b` (`ec8b_id`),
                KEY `idx_attachments_tenant` (`tenant_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Exception $e2) {
        error_log("Error creating attachments table: " . $e2->getMessage());
    }
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['description'] ?? '');
    
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $max_size = 20 * 1024 * 1024; // 20MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $error = 'Invalid file type. Please upload images, PDFs, or Word documents.';
        } elseif ($file['size'] > $max_size) {
            $error = 'File too large. Maximum size is 20MB.';
        } else {
            try {
                $upload_dir = '../../uploads/ec8b/attachments/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'ec8b_' . $ec8b_id . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $db_path = '/election/uploads/ec8b/attachments/' . $filename;
                    
                    $stmt = $db->prepare("
                        INSERT INTO ec8b_attachments (tenant_id, ec8b_id, user_id, file_name, file_url, file_size, file_type, description, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $tenant_id,
                        $ec8b_id,
                        $user_id,
                        $file['name'],
                        $db_path,
                        $file['size'],
                        $file['type'],
                        $description
                    ]);
                    
                    logActivity($user_id, 'ec8b_attachment_added', 
                        "Added attachment to EC8B ID: $ec8b_id - {$file['name']}",
                        'results_ec8b', $ec8b_id
                    );
                    
                    $message = "Attachment uploaded successfully!";
                    
                    // Refresh attachments
                    $stmt = $db->prepare("
                        SELECT * FROM ec8b_attachments 
                        WHERE ec8b_id = ? AND tenant_id = ?
                        ORDER BY created_at DESC
                    ");
                    $stmt->execute([$ec8b_id, $tenant_id]);
                    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $error = 'Failed to upload file.';
                }
            } catch (Exception $e) {
                $error = 'Failed to upload: ' . $e->getMessage();
                error_log("Attachment upload error: " . $e->getMessage());
            }
        }
    } else {
        $error = 'Please select a file to upload.';
    }
}

$page_title = 'EC8B Attachments';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.attach-container {
    max-width: 700px;
    margin: 0 auto;
}

.attach-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
    margin-bottom: 16px;
}

.attach-card .card-title {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.attach-card .card-title i {
    color: var(--primary);
    margin-right: 6px;
}

.ec8b-info {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 10px 14px;
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

.file-upload-area {
    border: 2px dashed var(--gray-300);
    border-radius: 10px;
    padding: 24px;
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
    background: var(--gray-50);
    margin-bottom: 12px;
}

.file-upload-area:hover {
    border-color: var(--primary);
    background: #EFF6FF;
}

.file-upload-area i {
    font-size: 2rem;
    color: var(--gray-400);
    display: block;
    margin-bottom: 6px;
}

.file-upload-area p {
    font-size: 0.85rem;
    color: var(--gray-600);
    margin: 0;
}

.file-upload-area .file-types {
    font-size: 0.65rem;
    color: var(--gray-400);
    margin-top: 4px;
}

.file-upload-area input[type="file"] {
    display: none;
}

.file-preview {
    display: none;
    margin-top: 8px;
    padding: 8px 12px;
    background: var(--gray-50);
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.file-preview.show {
    display: flex;
}

.file-preview .file-name {
    font-weight: 500;
    font-size: 0.8rem;
}

.file-preview .file-size {
    font-size: 0.6rem;
    color: var(--gray-400);
}

.form-group {
    margin-bottom: 12px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.75rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}

.form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    resize: vertical;
    min-height: 60px;
    transition: var(--transition);
}

.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.attachment-list {
    display: grid;
    gap: 8px;
    margin-top: 12px;
}

.attachment-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: var(--gray-50);
    border-radius: 6px;
    border: 1px solid var(--gray-200);
}

.attachment-item i {
    font-size: 1.2rem;
    color: var(--gray-500);
}

.attachment-item .info {
    flex: 1;
}

.attachment-item .info .name {
    font-weight: 500;
    font-size: 0.8rem;
}

.attachment-item .info .meta {
    font-size: 0.6rem;
    color: var(--gray-400);
}

.attachment-item .actions a {
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.6rem;
    text-decoration: none;
    transition: var(--transition);
}

.attachment-item .actions .btn-download {
    background: #EFF6FF;
    color: #3B82F6;
}

.attachment-item .actions .btn-download:hover {
    background: #DBEAFE;
}

.attachment-item .actions .btn-delete {
    background: #FEF2F2;
    color: #DC2626;
}

.attachment-item .actions .btn-delete:hover {
    background: #FEE2E2;
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
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.82rem;
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
    padding: 8px 20px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.82rem;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-group .btn-cancel:hover {
    background: var(--gray-200);
}

.empty-state {
    text-align: center;
    padding: 20px;
    color: var(--gray-400);
}

.empty-state i {
    font-size: 2rem;
    display: block;
    margin-bottom: 6px;
}

.empty-state p {
    margin: 0;
    font-size: 0.85rem;
}

@media (max-width: 768px) {
    .attach-card {
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
        <div class="attach-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-paperclip"></i> EC8B Attachments</h1>
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

            <div class="attach-card">
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
            </div>

            <!-- Upload Attachment -->
            <div class="attach-card">
                <div class="card-title"><i class="fas fa-upload"></i> Upload Attachment</div>

                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="file-upload-area" onclick="document.getElementById('attachmentFile').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to upload supporting document</p>
                        <div class="file-types">Images, PDFs, Word documents (Max 20MB)</div>
                        <input type="file" name="attachment" id="attachmentFile" required />
                    </div>
                    <div class="file-preview" id="filePreview">
                        <i class="fas fa-file"></i>
                        <div>
                            <div class="file-name" id="fileName">file.pdf</div>
                            <div class="file-size" id="fileSize">0 KB</div>
                        </div>
                        <button type="button" onclick="clearFile()" style="margin-left:auto;background:none;border:none;color:#EF4444;cursor:pointer;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="form-group">
                        <label>Description (Optional)</label>
                        <textarea name="description" placeholder="Describe this attachment..."></textarea>
                    </div>

                    <div class="btn-group">
                        <a href="ec8b-history.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn-upload" id="uploadBtn" disabled>
                            <i class="fas fa-upload"></i> Upload
                        </button>
                    </div>
                </form>
            </div>

            <!-- Attachments List -->
            <?php if (!empty($attachments)): ?>
                <div class="attach-card">
                    <div class="card-title">
                        <i class="fas fa-list"></i> Attachments
                        <span style="font-size:0.7rem;color:var(--gray-400);font-weight:400;margin-left:8px;">
                            (<?php echo count($attachments); ?>)
                        </span>
                    </div>

                    <div class="attachment-list">
                        <?php foreach ($attachments as $attachment): ?>
                            <div class="attachment-item">
                                <i class="fas <?php 
                                    echo strpos($attachment['file_type'], 'image') !== false ? 'fa-image' : 
                                        (strpos($attachment['file_type'], 'pdf') !== false ? 'fa-file-pdf' : 
                                        'fa-file'); 
                                ?>"></i>
                                <div class="info">
                                    <div class="name"><?php echo htmlspecialchars($attachment['file_name']); ?></div>
                                    <div class="meta">
                                        <?php echo number_format($attachment['file_size'] / 1024, 1); ?> KB
                                        <?php if ($attachment['description']): ?>
                                            • <?php echo htmlspecialchars($attachment['description']); ?>
                                        <?php endif; ?>
                                        • <?php echo date('M j, Y g:i A', strtotime($attachment['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="actions">
                                    <a href="<?php echo htmlspecialchars($attachment['file_url']); ?>" target="_blank" class="btn-download">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
document.getElementById('attachmentFile').addEventListener('change', function() {
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
    var fileInput = document.getElementById('attachmentFile');
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