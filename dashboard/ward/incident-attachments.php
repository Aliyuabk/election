<?php
// ============================================================
// WARD COORDINATOR - INCIDENT ATTACHMENTS
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
$incident_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($incident_id <= 0) {
    header('Location: incidents.php');
    exit();
}

// Get incident details
$incident = null;
try {
    $stmt = $db->prepare("
        SELECT i.*, pu.name as pu_name, e.name as election_name
        FROM incidents i
        LEFT JOIN polling_units pu ON i.pu_id = pu.id
        LEFT JOIN elections e ON i.election_id = e.id
        WHERE i.id = ? AND i.tenant_id = ? AND i.ward_id = ?
    ");
    $stmt->execute([$incident_id, $tenant_id, $ward_id]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching incident: " . $e->getMessage());
}

if (!$incident) {
    header('Location: incidents.php');
    exit();
}

// Get existing attachments
$attachments = [];
try {
    // Check if table exists
    $stmt = $db->query("SHOW TABLES LIKE 'incident_attachments'");
    if ($stmt->rowCount() > 0) {
        $stmt = $db->prepare("
            SELECT * FROM incident_attachments 
            WHERE incident_id = ? AND tenant_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$incident_id, $tenant_id]);
        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Table might not exist
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `incident_attachments` (
                `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id` bigint(20) UNSIGNED NOT NULL,
                `incident_id` bigint(20) UNSIGNED NOT NULL,
                `user_id` bigint(20) UNSIGNED NOT NULL,
                `file_name` varchar(255) NOT NULL,
                `file_url` varchar(500) NOT NULL,
                `file_size` bigint(20) UNSIGNED NOT NULL,
                `file_type` varchar(100) NOT NULL,
                `file_category` enum('photo','video','audio','document') NOT NULL,
                `description` text DEFAULT NULL,
                `gps_lat` decimal(10,8) DEFAULT NULL,
                `gps_lng` decimal(11,8) DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `idx_attachments_incident` (`incident_id`),
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
    $action = $_POST['action'] ?? '';
    $attachment_id = isset($_POST['attachment_id']) ? (int)$_POST['attachment_id'] : 0;
    
    if ($action === 'delete' && $attachment_id > 0) {
        try {
            $stmt = $db->prepare("DELETE FROM incident_attachments WHERE id = ? AND incident_id = ? AND tenant_id = ?");
            $stmt->execute([$attachment_id, $incident_id, $tenant_id]);
            $message = "Attachment deleted successfully!";
            
            // Refresh attachments
            $stmt = $db->prepare("SELECT * FROM incident_attachments WHERE incident_id = ? AND tenant_id = ? ORDER BY created_at DESC");
            $stmt->execute([$incident_id, $tenant_id]);
            $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = 'Failed to delete attachment: ' . $e->getMessage();
        }
    } elseif (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $category = $_POST['category'] ?? 'document';
        $description = trim($_POST['description'] ?? '');
        $gps_lat = $_POST['gps_lat'] ?? null;
        $gps_lng = $_POST['gps_lng'] ?? null;
        
        $allowed_types = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/mpeg', 'video/quicktime',
            'audio/mpeg', 'audio/wav', 'audio/ogg',
            'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        $max_size = 25 * 1024 * 1024; // 25MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $error = 'Invalid file type.';
        } elseif ($file['size'] > $max_size) {
            $error = 'File too large. Maximum size is 25MB.';
        } else {
            try {
                $upload_dir = '../../uploads/incidents/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'incident_' . $incident_id . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $db_path = '/election/uploads/incidents/' . $filename;
                    
                    $stmt = $db->prepare("
                        INSERT INTO incident_attachments (tenant_id, incident_id, user_id, file_name, file_url, file_size, file_type, file_category, description, gps_lat, gps_lng, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $tenant_id,
                        $incident_id,
                        $user_id,
                        $file['name'],
                        $db_path,
                        $file['size'],
                        $file['type'],
                        $category,
                        $description,
                        $gps_lat ?: null,
                        $gps_lng ?: null
                    ]);
                    
                    logActivity($user_id, 'incident_attachment_added', 
                        "Added attachment to incident #$incident_id - {$file['name']}",
                        'incidents', $incident_id
                    );
                    
                    $message = "Attachment uploaded successfully!";
                    
                    // Refresh attachments
                    $stmt = $db->prepare("SELECT * FROM incident_attachments WHERE incident_id = ? AND tenant_id = ? ORDER BY created_at DESC");
                    $stmt->execute([$incident_id, $tenant_id]);
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

$page_title = 'Incident Attachments';
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
    padding: 20px 24px;
    margin-bottom: 16px;
}

.attach-card .card-title {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 10px;
    padding-bottom: 6px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.attach-card .card-title i {
    color: var(--primary);
    margin-right: 6px;
}

.incident-info {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 10px 14px;
    margin-bottom: 16px;
}

.incident-info .label {
    font-size: 0.6rem;
    color: var(--gray-500);
    display: block;
}

.incident-info .value {
    font-weight: 500;
    color: var(--gray-800);
    font-size: 0.85rem;
}

.file-upload-area {
    border: 2px dashed var(--gray-300);
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
    background: var(--gray-50);
    margin-bottom: 10px;
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
    margin-bottom: 10px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.75rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}

.form-group select,
.form-group textarea,
.form-group input[type="text"] {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    transition: var(--transition);
    background: white;
}

.form-group select:focus,
.form-group textarea:focus,
.form-group input[type="text"]:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.form-group textarea {
    resize: vertical;
    min-height: 50px;
}

.attachment-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
}

.attachment-item {
    background: var(--gray-50);
    border-radius: 8px;
    border: 1px solid var(--gray-200);
    overflow: hidden;
    transition: var(--transition);
}

.attachment-item:hover {
    border-color: var(--primary);
}

.attachment-item .thumb {
    width: 100%;
    height: 120px;
    background: var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: var(--gray-400);
}

.attachment-item .thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.attachment-item .info {
    padding: 8px 10px;
}

.attachment-item .info .name {
    font-weight: 500;
    font-size: 0.75rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.attachment-item .info .meta {
    font-size: 0.6rem;
    color: var(--gray-400);
}

.attachment-item .info .category-badge {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 4px;
    font-size: 0.5rem;
    font-weight: 600;
    background: #EFF6FF;
    color: #1E40AF;
}

.attachment-item .actions {
    padding: 4px 10px 8px;
    display: flex;
    gap: 4px;
}

.attachment-item .actions a {
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.6rem;
    text-decoration: none;
    transition: var(--transition);
}

.attachment-item .actions .btn-view {
    background: #EFF6FF;
    color: #3B82F6;
}

.attachment-item .actions .btn-delete {
    background: #FEF2F2;
    color: #DC2626;
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
    gap: 8px;
    flex-wrap: wrap;
}

.btn-group button {
    padding: 8px 20px;
    border: none;
    border-radius: 6px;
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
    border-radius: 6px;
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
        padding: 14px 16px;
    }
    .attachment-grid {
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
        <div class="attach-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-paperclip"></i> Incident Attachments</h1>
                    <p class="subtitle">
                        <i class="fas fa-exclamation-triangle"></i> 
                        #<?php echo $incident_id; ?> - <?php echo htmlspecialchars($incident['title']); ?>
                    </p>
                </div>
                <div class="actions">
                    <a href="incident-view.php?id=<?php echo $incident_id; ?>" class="btn-secondary-sm">
                        <i class="fas fa-eye"></i> Back to Incident
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
                <div class="card-title"><i class="fas fa-info-circle"></i> Incident Information</div>
                
                <div class="incident-info">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                        <div>
                            <span class="label">Type</span>
                            <span class="value"><?php echo $incident['incident_type']; ?></span>
                        </div>
                        <div>
                            <span class="label">Status</span>
                            <span class="value">
                                <span class="status-badge <?php echo $incident['status']; ?>">
                                    <span class="dot"></span>
                                    <?php echo ucfirst($incident['status']); ?>
                                </span>
                            </span>
                        </div>
                        <div>
                            <span class="label">Location</span>
                            <span class="value"><?php echo htmlspecialchars($incident['pu_name'] ?? 'N/A'); ?></span>
                        </div>
                        <div>
                            <span class="label">Election</span>
                            <span class="value"><?php echo htmlspecialchars($incident['election_name'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upload Attachment -->
            <div class="attach-card">
                <div class="card-title"><i class="fas fa-upload"></i> Upload Attachment</div>

                <form method="POST" action="" enctype="multipart/form-data" id="attachForm">
                    <div class="file-upload-area" onclick="document.getElementById('attachFile').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to upload file</p>
                        <div class="file-types">Images, Videos, Audio, Documents (Max 25MB)</div>
                        <input type="file" name="attachment" id="attachFile" required />
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
                        <label>Category <span class="required">*</span></label>
                        <select name="category" required>
                            <option value="">Select category...</option>
                            <option value="photo">Photo</option>
                            <option value="video">Video</option>
                            <option value="audio">Audio</option>
                            <option value="document">Document</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Description (Optional)</label>
                        <textarea name="description" placeholder="Describe this attachment..."></textarea>
                    </div>

                    <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <div class="form-group">
                            <label>GPS Latitude</label>
                            <input type="text" name="gps_lat" placeholder="e.g., 6.5244" />
                        </div>
                        <div class="form-group">
                            <label>GPS Longitude</label>
                            <input type="text" name="gps_lng" placeholder="e.g., 3.3792" />
                        </div>
                    </div>

                    <div class="btn-group">
                        <a href="incident-view.php?id=<?php echo $incident_id; ?>" class="btn-cancel">
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

                    <div class="attachment-grid">
                        <?php foreach ($attachments as $attach): 
                            $icon_map = [
                                'photo' => 'fa-image',
                                'video' => 'fa-video',
                                'audio' => 'fa-music',
                                'document' => 'fa-file'
                            ];
                            $icon = $icon_map[$attach['file_category']] ?? 'fa-file';
                            $is_image = strpos($attach['file_type'], 'image') !== false;
                        ?>
                            <div class="attachment-item">
                                <div class="thumb">
                                    <?php if ($is_image): ?>
                                        <img src="<?php echo htmlspecialchars($attach['file_url']); ?>" alt="<?php echo htmlspecialchars($attach['file_name']); ?>" />
                                    <?php else: ?>
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="info">
                                    <div class="name" title="<?php echo htmlspecialchars($attach['file_name']); ?>">
                                        <?php echo htmlspecialchars($attach['file_name']); ?>
                                    </div>
                                    <div class="meta">
                                        <span class="category-badge"><?php echo ucfirst($attach['file_category']); ?></span>
                                        <?php echo number_format($attach['file_size'] / 1024, 1); ?> KB
                                    </div>
                                    <?php if ($attach['description']): ?>
                                        <div style="font-size:0.55rem;color:var(--gray-500);margin-top:2px;">
                                            <?php echo htmlspecialchars($attach['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="actions">
                                    <a href="<?php echo htmlspecialchars($attach['file_url']); ?>" target="_blank" class="btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete this attachment?')">
                                        <input type="hidden" name="action" value="delete" />
                                        <input type="hidden" name="attachment_id" value="<?php echo $attach['id']; ?>" />
                                        <button type="submit" class="btn-delete" style="background:none;border:none;cursor:pointer;padding:2px 8px;">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
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
document.getElementById('attachFile').addEventListener('change', function() {
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
    var fileInput = document.getElementById('attachFile');
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