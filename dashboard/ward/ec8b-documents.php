<?php
// ============================================================
// WARD COORDINATOR - EC8B SUPPORTING DOCUMENTS
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
// GET EC8B ID
// ============================================================
$ec8b_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ec8b_id <= 0) {
    header('Location: ec8b-history.php');
    exit();
}

// ============================================================
// FETCH EC8B DETAILS
// ============================================================
$ec8b = null;
$documents = [];
$error_message = '';

try {
    $stmt = $db->prepare("
        SELECT * FROM results_ec8b 
        WHERE id = ? AND tenant_id = ? AND ward_id = ?
    ");
    $stmt->execute([$ec8b_id, $tenant_id, $ward_id]);
    $ec8b = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ec8b) {
        header('Location: ec8b-history.php?error=notfound');
        exit();
    }
    
    // Parse documents
    $documents = json_decode($ec8b['attachments_json'] ?? '[]', true);
    if (!is_array($documents)) {
        $documents = [];
    }
    
} catch (Exception $e) {
    error_log("Error fetching EC8B: " . $e->getMessage());
    header('Location: ec8b-history.php?error=db');
    exit();
}

// ============================================================
// HANDLE DOCUMENT UPLOAD
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $file = $_FILES['document'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $max_size = 10 * 1024 * 1024; // 10MB
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_message = "Upload error: " . $file['error'];
    } elseif (!in_array($file['type'], $allowed_types)) {
        $error_message = "File type not allowed. Please upload images, PDFs, or Word documents.";
    } elseif ($file['size'] > $max_size) {
        $error_message = "File size exceeds 10MB limit.";
    } else {
        try {
            $upload_dir = '../../uploads/ec8b/documents/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $filename = 'ec8b_' . $ec8b_id . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            $filepath = $upload_dir . $filename;
            $file_url = '/election/uploads/ec8b/documents/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $documents[] = [
                    'url' => $file_url,
                    'name' => $file['name'],
                    'size' => $file['size'],
                    'type' => $file['type'],
                    'uploaded_at' => date('Y-m-d H:i:s')
                ];
                
                $stmt = $db->prepare("
                    UPDATE results_ec8b 
                    SET attachments_json = ? 
                    WHERE id = ? AND tenant_id = ? AND ward_id = ?
                ");
                $stmt->execute([json_encode($documents), $ec8b_id, $tenant_id, $ward_id]);
                
                logActivity($user_id, 'ec8b_document_uploaded', "Uploaded document to EC8B ID: $ec8b_id", 'results_ec8b', $ec8b_id);
                
                $success_message = "Document uploaded successfully!";
                header('Location: ec8b-documents.php?id=' . $ec8b_id . '&success=' . urlencode($success_message));
                exit();
                
            } else {
                $error_message = "Failed to upload file.";
            }
            
        } catch (Exception $e) {
            $error_message = "Error uploading document: " . $e->getMessage();
            error_log("EC8B document upload error: " . $e->getMessage());
        }
    }
}

// ============================================================
// HANDLE DOCUMENT REMOVAL
// ============================================================
if (isset($_GET['remove']) && isset($_GET['index'])) {
    $index = (int)$_GET['index'];
    if (isset($documents[$index])) {
        unset($documents[$index]);
        $documents = array_values($documents);
        
        $stmt = $db->prepare("
            UPDATE results_ec8b 
            SET attachments_json = ? 
            WHERE id = ? AND tenant_id = ? AND ward_id = ?
        ");
        $stmt->execute([json_encode($documents), $ec8b_id, $tenant_id, $ward_id]);
        
        logActivity($user_id, 'ec8b_document_removed', "Removed document from EC8B ID: $ec8b_id", 'results_ec8b', $ec8b_id);
        
        $success_message = "Document removed successfully.";
        header('Location: ec8b-documents.php?id=' . $ec8b_id . '&success=' . urlencode($success_message));
        exit();
    }
}

$page_title = 'EC8B Supporting Documents';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.documents-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.documents-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.documents-header h2 i {
    color: var(--primary);
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

.documents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
}
.document-item {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 12px;
    text-align: center;
    position: relative;
    transition: var(--transition);
}
.document-item:hover {
    box-shadow: var(--shadow-hover);
}
.document-item .icon {
    font-size: 3rem;
    color: var(--gray-400);
    margin-bottom: 8px;
}
.document-item .name {
    font-size: 0.7rem;
    color: var(--gray-600);
    word-break: break-all;
}
.document-item .size {
    font-size: 0.6rem;
    color: var(--gray-400);
}
.document-item .remove-btn {
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
.document-item .remove-btn:hover {
    background: #FECACA;
}

.ec8b-info {
    background: var(--gray-50);
    border-radius: var(--radius);
    padding: 16px;
    margin-bottom: 20px;
}
.ec8b-info .info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 8px 16px;
}
.ec8b-info .info-grid .item {
    font-size: 0.85rem;
    padding: 4px 0;
}
.ec8b-info .info-grid .item .label {
    color: var(--gray-500);
    font-weight: 500;
}
.ec8b-info .info-grid .item .value {
    color: var(--gray-800);
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

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--gray-400);
}
.empty-state i {
    font-size: 2rem;
    display: block;
    margin-bottom: 8px;
}

@media (max-width: 768px) {
    .ec8b-info .info-grid {
        grid-template-columns: 1fr;
    }
    .upload-section .drop-zone {
        padding: 30px 20px;
    }
    .documents-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="documents-header">
            <div>
                <h2><i class="fas fa-folder-open"></i> EC8B Supporting Documents</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward • Form #<?php echo $ec8b_id; ?>
                </p>
            </div>
            <div>
                <a href="ec8b-details.php?id=<?php echo $ec8b_id; ?>" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
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

        <?php if ($ec8b): ?>
            <!-- EC8B Information -->
            <div class="ec8b-info">
                <div class="info-grid">
                    <div class="item">
                        <span class="label">Form ID</span><br>
                        <span class="value">#<?php echo $ec8b['id']; ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Status</span><br>
                        <span class="value"><?php echo ucfirst($ec8b['status']); ?></span>
                    </div>
                    <div class="item">
                        <span class="label">Documents</span><br>
                        <span class="value"><?php echo count($documents); ?> file(s)</span>
                    </div>
                </div>
            </div>

            <!-- Upload Section -->
            <div class="upload-section">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="drop-zone" id="dropZone">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p><strong>Click to upload</strong> or drag and drop supporting documents</p>
                        <div class="file-types">Supported: JPG, PNG, PDF, DOC, DOCX (Max 10MB)</div>
                        <input type="file" name="document" id="fileInput" accept="image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                    </div>
                    <div style="margin-top:12px;text-align:center;">
                        <button type="submit" class="btn-primary" id="uploadBtn" disabled>
                            <i class="fas fa-upload"></i> Upload Document
                        </button>
                    </div>
                </form>
            </div>

            <!-- Documents List -->
            <?php if (count($documents) > 0): ?>
                <h3 style="font-size:0.95rem;margin:0 0 12px;">
                    <i class="fas fa-files"></i> Documents (<?php echo count($documents); ?>)
                </h3>
                <div class="documents-grid">
                    <?php foreach ($documents as $index => $doc): 
                        $icon = 'fa-file';
                        if (strpos($doc['type'], 'image/') === 0) $icon = 'fa-file-image';
                        elseif (strpos($doc['type'], 'pdf') !== false) $icon = 'fa-file-pdf';
                        elseif (strpos($doc['type'], 'word') !== false || strpos($doc['type'], 'msword') !== false) $icon = 'fa-file-word';
                    ?>
                        <div class="document-item">
                            <div class="icon"><i class="fas <?php echo $icon; ?>"></i></div>
                            <div class="name"><?php echo htmlspecialchars(substr($doc['name'], 0, 25)) . (strlen($doc['name']) > 25 ? '...' : ''); ?></div>
                            <div class="size"><?php echo round($doc['size'] / 1024, 1); ?> KB</div>
                            <a href="<?php echo htmlspecialchars($doc['url']); ?>" target="_blank" class="btn-secondary-sm" style="font-size:0.6rem;padding:2px 10px;margin-top:4px;display:inline-block;">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="ec8b-documents.php?id=<?php echo $ec8b_id; ?>&remove=1&index=<?php echo $index; ?>" 
                               class="remove-btn" onclick="return confirm('Remove this document?')" title="Remove">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>No supporting documents uploaded yet</p>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div style="text-align:center;padding:60px 20px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);">
                <i class="fas fa-file-alt" style="font-size:4rem;color:var(--gray-300);"></i>
                <h4 style="margin:16px 0 8px;">Form Not Found</h4>
                <p style="color:var(--gray-500);">The EC8B form does not exist.</p>
                <a href="ec8b-history.php" class="btn-primary-sm" style="display:inline-block;margin-top:12px;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
document.getElementById('fileInput').addEventListener('change', function() {
    const uploadBtn = document.getElementById('uploadBtn');
    if (this.files.length > 0) {
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload ' + this.files[0].name;
    } else {
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload Document';
    }
});

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