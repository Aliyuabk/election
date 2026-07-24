<?php
// ============================================================
// WARD COORDINATOR - INCIDENT ATTACHMENTS
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
// GET INCIDENT ID
// ============================================================
$incident_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($incident_id <= 0) {
    header('Location: incidents.php');
    exit();
}

// ============================================================
// FETCH INCIDENT DETAILS
// ============================================================
$incident = null;
$error_message = '';

try {
    $stmt = $db->prepare("
        SELECT 
            i.*,
            u.full_name as reporter_name,
            pu.name as pu_name,
            pu.code as pu_code
        FROM incidents i
        LEFT JOIN users u ON i.reporter_id = u.id
        LEFT JOIN polling_units pu ON i.pu_id = pu.id
        WHERE i.id = ? AND i.tenant_id = ? AND i.ward_id = ?
    ");
    $stmt->execute([$incident_id, $tenant_id, $ward_id]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$incident) {
        header('Location: incidents.php?error=notfound');
        exit();
    }
    
} catch (Exception $e) {
    error_log("Error fetching incident: " . $e->getMessage());
    header('Location: incidents.php?error=db');
    exit();
}

// ============================================================
// HANDLE ATTACHMENT UPLOAD
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'video/mp4', 'audio/mpeg'];
        $max_size = 10 * 1024 * 1024; // 10MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $error_message = "File type not allowed. Please upload images, PDFs, videos, or audio files.";
        } elseif ($file['size'] > $max_size) {
            $error_message = "File size exceeds 10MB limit.";
        } else {
            try {
                // Create upload directory
                $upload_dir = '../../uploads/incidents/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $filename = 'incident_' . $incident_id . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
                $filepath = $upload_dir . $filename;
                $file_url = '/election/uploads/incidents/' . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Update incident with attachment
                    $current_photos = json_decode($incident['photo_urls_json'] ?? '[]', true);
                    if (!is_array($current_photos)) {
                        $current_photos = [];
                    }
                    $current_photos[] = $file_url;
                    $photo_urls_json = json_encode($current_photos);
                    
                    $stmt = $db->prepare("
                        UPDATE incidents 
                        SET photo_urls_json = ?, updated_at = NOW() 
                        WHERE id = ? AND tenant_id = ? AND ward_id = ?
                    ");
                    $stmt->execute([$photo_urls_json, $incident_id, $tenant_id, $ward_id]);
                    
                    logActivity($user_id, 'incident_attachment_added', "Added attachment to incident ID: $incident_id", 'incidents', $incident_id);
                    
                    $success_message = "Attachment uploaded successfully!";
                    
                    // Refresh incident data
                    $stmt = $db->prepare("
                        SELECT 
                            i.*,
                            u.full_name as reporter_name,
                            pu.name as pu_name,
                            pu.code as pu_code
                        FROM incidents i
                        LEFT JOIN users u ON i.reporter_id = u.id
                        LEFT JOIN polling_units pu ON i.pu_id = pu.id
                        WHERE i.id = ? AND i.tenant_id = ? AND i.ward_id = ?
                    ");
                    $stmt->execute([$incident_id, $tenant_id, $ward_id]);
                    $incident = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                } else {
                    $error_message = "Failed to upload file.";
                }
                
            } catch (Exception $e) {
                $error_message = "Error uploading attachment: " . $e->getMessage();
                error_log("Attachment upload error: " . $e->getMessage());
            }
        }
    } else {
        $error_message = "No file selected or upload error occurred.";
    }
}

// ============================================================
// HANDLE ATTACHMENT REMOVAL
// ============================================================
if (isset($_GET['remove']) && isset($_GET['url'])) {
    $remove_url = urldecode($_GET['url']);
    $current_photos = json_decode($incident['photo_urls_json'] ?? '[]', true);
    
    if (($key = array_search($remove_url, $current_photos)) !== false) {
        unset($current_photos[$key]);
        $current_photos = array_values($current_photos);
        $photo_urls_json = json_encode($current_photos);
        
        $stmt = $db->prepare("
            UPDATE incidents 
            SET photo_urls_json = ?, updated_at = NOW() 
            WHERE id = ? AND tenant_id = ? AND ward_id = ?
        ");
        $stmt->execute([$photo_urls_json, $incident_id, $tenant_id, $ward_id]);
        
        logActivity($user_id, 'incident_attachment_removed', "Removed attachment from incident ID: $incident_id", 'incidents', $incident_id);
        
        $success_message = "Attachment removed successfully.";
        header('Location: incident-attachments.php?id=' . $incident_id . '&success=' . urlencode($success_message));
        exit();
    }
}

$page_title = 'Incident Attachments';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.attachment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.attachment-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.attachment-header h2 i {
    color: var(--primary);
}

.incident-info {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    margin-bottom: 20px;
}
.incident-info .info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 8px 16px;
}
.incident-info .info-grid .item {
    font-size: 0.85rem;
    padding: 4px 0;
}
.incident-info .info-grid .item .label {
    color: var(--gray-500);
    font-weight: 500;
}
.incident-info .info-grid .item .value {
    color: var(--gray-800);
}

.upload-section {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
    margin-bottom: 20px;
}
.upload-section .drop-zone {
    border: 2px dashed var(--gray-300);
    border-radius: var(--radius);
    padding: 40px;
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
}
.upload-section .drop-zone:hover {
    border-color: var(--primary);
    background: #F8FAFC;
}
.upload-section .drop-zone i {
    font-size: 3rem;
    color: var(--gray-400);
    margin-bottom: 12px;
}
.upload-section .drop-zone p {
    color: var(--gray-500);
    margin: 0;
}
.upload-section .drop-zone .file-types {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 4px;
}
.upload-section .drop-zone input[type="file"] {
    display: none;
}

.attachments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 12px;
}
.attachment-item {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 12px;
    text-align: center;
    position: relative;
    transition: var(--transition);
}
.attachment-item:hover {
    box-shadow: var(--shadow-hover);
}
.attachment-item .preview {
    width: 100%;
    height: 120px;
    border-radius: 4px;
    background: var(--gray-100);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: var(--gray-400);
    overflow: hidden;
}
.attachment-item .preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.attachment-item .name {
    font-size: 0.7rem;
    color: var(--gray-600);
    margin-top: 6px;
    word-break: break-all;
}
.attachment-item .remove-btn {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: none;
    background: #FEE2E2;
    color: #EF4444;
    cursor: pointer;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
}
.attachment-item .remove-btn:hover {
    background: #FECACA;
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

.status-badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
}

@media (max-width: 768px) {
    .incident-info .info-grid {
        grid-template-columns: 1fr;
    }
    .attachments-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="attachment-header">
            <div>
                <h2><i class="fas fa-paperclip"></i> Incident Attachments</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward • Incident #<?php echo $incident_id; ?>
                </p>
            </div>
            <div>
                <a href="incident-details.php?id=<?php echo $incident_id; ?>" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Incident
                </a>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($incident): ?>
            <!-- Incident Information -->
            <div class="incident-info">
                <h3 style="margin:0 0 12px;font-size:0.95rem;">
                    <i class="fas fa-info-circle"></i> Incident Details
                </h3>
                <div class="info-grid">
                    <div class="item">
                        <span class="label">Title</span><br>
                        <span class="value"><?php echo htmlspecialchars($incident['title']); ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Type</span><br>
                        <span class="value"><?php echo ucfirst(str_replace('_', ' ', $incident['incident_type'] ?? 'Unknown')); ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Status</span><br>
                        <span class="status-badge <?php echo $incident['status']; ?>"><?php echo ucfirst($incident['status']); ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Severity</span><br>
                        <span class="value"><?php echo ucfirst($incident['severity'] ?? 'Medium'); ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Reported By</span><br>
                        <span class="value"><?php echo htmlspecialchars($incident['reporter_name'] ?? 'Unknown'); ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Polling Unit</span><br>
                        <span class="value"><?php echo htmlspecialchars($incident['pu_name'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Upload Section -->
            <div class="upload-section">
                <form method="POST" action="" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="action" value="upload">
                    <div class="drop-zone" id="dropZone">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p><strong>Click to upload</strong> or drag and drop files</p>
                        <div class="file-types">Supported: JPG, PNG, GIF, PDF, MP4, MP3 (Max 10MB)</div>
                        <input type="file" name="attachment" id="fileInput" accept="image/*,application/pdf,video/*,audio/*">
                    </div>
                    <div style="margin-top:12px;text-align:center;">
                        <button type="submit" class="btn-primary-sm" id="uploadBtn" disabled>
                            <i class="fas fa-upload"></i> Upload Attachment
                        </button>
                    </div>
                </form>
            </div>

            <!-- Attachments List -->
            <?php 
            $attachments = json_decode($incident['photo_urls_json'] ?? '[]', true);
            if (!empty($attachments)): 
            ?>
                <h3 style="font-size:0.95rem;margin:0 0 12px;">
                    <i class="fas fa-files"></i> Attachments (<?php echo count($attachments); ?>)
                </h3>
                <div class="attachments-grid">
                    <?php foreach ($attachments as $url): 
                        $is_image = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $url);
                        $is_pdf = preg_match('/\.pdf$/i', $url);
                        $is_video = preg_match('/\.(mp4|webm|avi|mov)$/i', $url);
                        $is_audio = preg_match('/\.(mp3|wav|ogg)$/i', $url);
                        $filename = basename($url);
                    ?>
                        <div class="attachment-item">
                            <div class="preview">
                                <?php if ($is_image): ?>
                                    <img src="<?php echo htmlspecialchars($url); ?>" alt="<?php echo htmlspecialchars($filename); ?>">
                                <?php elseif ($is_pdf): ?>
                                    <i class="fas fa-file-pdf"></i>
                                <?php elseif ($is_video): ?>
                                    <i class="fas fa-file-video"></i>
                                <?php elseif ($is_audio): ?>
                                    <i class="fas fa-file-audio"></i>
                                <?php else: ?>
                                    <i class="fas fa-file"></i>
                                <?php endif; ?>
                            </div>
                            <div class="name"><?php echo htmlspecialchars(substr($filename, 0, 20)) . (strlen($filename) > 20 ? '...' : ''); ?></div>
                            <a href="incident-attachments.php?id=<?php echo $incident_id; ?>&remove=1&url=<?php echo urlencode($url); ?>" 
                               class="remove-btn" onclick="return confirm('Remove this attachment?')" title="Remove">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:40px 20px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);">
                    <i class="fas fa-paperclip" style="font-size:2rem;color:var(--gray-300);display:block;margin-bottom:8px;"></i>
                    <p style="color:var(--gray-400);font-size:0.9rem;">No attachments uploaded yet</p>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div style="text-align:center;padding:60px 20px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);">
                <i class="fas fa-exclamation-triangle" style="font-size:4rem;color:var(--gray-300);"></i>
                <h4 style="margin:16px 0 8px;">Incident Not Found</h4>
                <p style="color:var(--gray-500);">The incident you're looking for does not exist.</p>
                <a href="incidents.php" class="btn-primary-sm" style="display:inline-block;margin-top:12px;">
                    <i class="fas fa-arrow-left"></i> Back to Incidents
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// File input handling
document.getElementById('fileInput').addEventListener('change', function() {
    const uploadBtn = document.getElementById('uploadBtn');
    if (this.files.length > 0) {
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload ' + this.files[0].name;
    } else {
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload Attachment';
    }
});

// Drag and drop functionality
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');

dropZone.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.style.borderColor = '#3B82F6';
    this.style.background = '#EFF6FF';
});

dropZone.addEventListener('dragleave', function(e) {
    e.preventDefault();
    this.style.borderColor = '#D1D5DB';
    this.style.background = 'transparent';
});

dropZone.addEventListener('drop', function(e) {
    e.preventDefault();
    this.style.borderColor = '#D1D5DB';
    this.style.background = 'transparent';
    
    if (e.dataTransfer.files.length > 0) {
        fileInput.files = e.dataTransfer.files;
        const event = new Event('change');
        fileInput.dispatchEvent(event);
    }
});

dropZone.addEventListener('click', function() {
    fileInput.click();
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