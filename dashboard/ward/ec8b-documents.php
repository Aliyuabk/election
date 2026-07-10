<?php
// ============================================================
// WARD COORDINATOR - EC8B SUPPORTING DOCUMENTS
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

// Get documents
$documents = [];
try {
    // Check if table exists
    $stmt = $db->query("SHOW TABLES LIKE 'ec8b_documents'");
    if ($stmt->rowCount() > 0) {
        $stmt = $db->prepare("
            SELECT * FROM ec8b_documents 
            WHERE ec8b_id = ? AND tenant_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$ec8b_id, $tenant_id]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Table might not exist
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ec8b_documents` (
                `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id` bigint(20) UNSIGNED NOT NULL,
                `ec8b_id` bigint(20) UNSIGNED NOT NULL,
                `user_id` bigint(20) UNSIGNED NOT NULL,
                `document_name` varchar(255) NOT NULL,
                `document_type` enum('result_sheet','supporting_document','photo_evidence','witness_statement','other') NOT NULL,
                `file_url` varchar(500) NOT NULL,
                `file_size` bigint(20) UNSIGNED NOT NULL,
                `file_type` varchar(100) NOT NULL,
                `description` text DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `idx_documents_ec8b` (`ec8b_id`),
                KEY `idx_documents_tenant` (`tenant_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Exception $e2) {
        error_log("Error creating documents table: " . $e2->getMessage());
    }
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $doc_id = isset($_POST['doc_id']) ? (int)$_POST['doc_id'] : 0;
    
    if ($action === 'delete' && $doc_id > 0) {
        try {
            $stmt = $db->prepare("DELETE FROM ec8b_documents WHERE id = ? AND ec8b_id = ? AND tenant_id = ?");
            $stmt->execute([$doc_id, $ec8b_id, $tenant_id]);
            $message = "Document deleted successfully!";
            
            // Refresh documents
            $stmt = $db->prepare("SELECT * FROM ec8b_documents WHERE ec8b_id = ? AND tenant_id = ? ORDER BY created_at DESC");
            $stmt->execute([$ec8b_id, $tenant_id]);
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = 'Failed to delete document: ' . $e->getMessage();
        }
    } elseif (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['document'];
        $document_type = $_POST['document_type'] ?? 'supporting_document';
        $description = trim($_POST['description'] ?? '');
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'];
        $max_size = 25 * 1024 * 1024; // 25MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $error = 'Invalid file type. Please upload images, PDFs, Word documents, or text files.';
        } elseif ($file['size'] > $max_size) {
            $error = 'File too large. Maximum size is 25MB.';
        } else {
            try {
                $upload_dir = '../../uploads/ec8b/documents/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'ec8b_' . $ec8b_id . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $db_path = '/election/uploads/ec8b/documents/' . $filename;
                    
                    $stmt = $db->prepare("
                        INSERT INTO ec8b_documents (tenant_id, ec8b_id, user_id, document_name, document_type, file_url, file_size, file_type, description, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $tenant_id,
                        $ec8b_id,
                        $user_id,
                        $file['name'],
                        $document_type,
                        $db_path,
                        $file['size'],
                        $file['type'],
                        $description
                    ]);
                    
                    logActivity($user_id, 'ec8b_document_added', 
                        "Added document to EC8B ID: $ec8b_id - {$file['name']}",
                        'results_ec8b', $ec8b_id
                    );
                    
                    $message = "Document uploaded successfully!";
                    
                    // Refresh documents
                    $stmt = $db->prepare("SELECT * FROM ec8b_documents WHERE ec8b_id = ? AND tenant_id = ? ORDER BY created_at DESC");
                    $stmt->execute([$ec8b_id, $tenant_id]);
                    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $error = 'Failed to upload file.';
                }
            } catch (Exception $e) {
                $error = 'Failed to upload: ' . $e->getMessage();
                error_log("Document upload error: " . $e->getMessage());
            }
        }
    } else {
        $error = 'Please select a file to upload.';
    }
}

$page_title = 'EC8B Documents';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.documents-container {
    max-width: 800px;
    margin: 0 auto;
}

.doc-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px 24px;
    margin-bottom: 16px;
}

.doc-card .card-title {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 10px;
    padding-bottom: 6px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.doc-card .card-title i {
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
    font-size: 1.8rem;
    color: var(--gray-400);
    display: block;
    margin-bottom: 4px;
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
.form-group textarea {
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
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.form-group textarea {
    resize: vertical;
    min-height: 50px;
}

.doc-list {
    display: grid;
    gap: 8px;
}

.doc-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: var(--gray-50);
    border-radius: 6px;
    border: 1px solid var(--gray-200);
}

.doc-item .doc-icon {
    font-size: 1.2rem;
    color: var(--gray-500);
}

.doc-item .info {
    flex: 1;
}

.doc-item .info .name {
    font-weight: 500;
    font-size: 0.8rem;
}

.doc-item .info .meta {
    font-size: 0.6rem;
    color: var(--gray-400);
}

.doc-item .info .type-badge {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 4px;
    font-size: 0.5rem;
    font-weight: 600;
    background: #EFF6FF;
    color: #1E40AF;
}

.doc-item .actions a {
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.6rem;
    text-decoration: none;
    transition: var(--transition);
}

.doc-item .actions .btn-view {
    background: #EFF6FF;
    color: #3B82F6;
}

.doc-item .actions .btn-delete {
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
    padding: 16px;
    color: var(--gray-400);
    font-size: 0.85rem;
}

.empty-state i {
    font-size: 1.5rem;
    display: block;
    margin-bottom: 4px;
}

@media (max-width: 768px) {
    .doc-card {
        padding: 14px 16px;
    }
    .btn-group {
        flex-direction: column;
    }
    .btn-group button,
    .btn-group .btn-cancel {
        width: 100%;
        justify-content: center;
    }
    .doc-item {
        flex-wrap: wrap;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="documents-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-folder-open"></i> EC8B Documents</h1>
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

            <div class="doc-card">
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

            <!-- Upload Document -->
            <div class="doc-card">
                <div class="card-title"><i class="fas fa-upload"></i> Upload Supporting Document</div>

                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="file-upload-area" onclick="document.getElementById('docFile').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to upload supporting document</p>
                        <div class="file-types">Images, PDFs, Word documents, Text files (Max 25MB)</div>
                        <input type="file" name="document" id="docFile" required />
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
                        <label>Document Type <span class="required">*</span></label>
                        <select name="document_type" required>
                            <option value="">Select type...</option>
                            <option value="result_sheet">Result Sheet</option>
                            <option value="supporting_document">Supporting Document</option>
                            <option value="photo_evidence">Photo Evidence</option>
                            <option value="witness_statement">Witness Statement</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Description (Optional)</label>
                        <textarea name="description" placeholder="Describe this document..."></textarea>
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

            <!-- Documents List -->
            <?php if (!empty($documents)): ?>
                <div class="doc-card">
                    <div class="card-title">
                        <i class="fas fa-list"></i> Uploaded Documents
                        <span style="font-size:0.7rem;color:var(--gray-400);font-weight:400;margin-left:8px;">
                            (<?php echo count($documents); ?>)
                        </span>
                    </div>

                    <div class="doc-list">
                        <?php foreach ($documents as $doc): 
                            $type_labels = [
                                'result_sheet' => 'Result Sheet',
                                'supporting_document' => 'Supporting Doc',
                                'photo_evidence' => 'Photo Evidence',
                                'witness_statement' => 'Witness Statement',
                                'other' => 'Other'
                            ];
                            $icon_map = [
                                'image' => 'fa-image',
                                'pdf' => 'fa-file-pdf',
                                'word' => 'fa-file-word',
                                'text' => 'fa-file-alt',
                                'default' => 'fa-file'
                            ];
                            $icon = 'fa-file';
                            if (strpos($doc['file_type'], 'image') !== false) $icon = 'fa-image';
                            elseif (strpos($doc['file_type'], 'pdf') !== false) $icon = 'fa-file-pdf';
                            elseif (strpos($doc['file_type'], 'word') !== false) $icon = 'fa-file-word';
                            elseif (strpos($doc['file_type'], 'text') !== false) $icon = 'fa-file-alt';
                        ?>
                            <div class="doc-item">
                                <div class="doc-icon"><i class="fas <?php echo $icon; ?>"></i></div>
                                <div class="info">
                                    <div class="name"><?php echo htmlspecialchars($doc['document_name']); ?></div>
                                    <div class="meta">
                                        <span class="type-badge"><?php echo $type_labels[$doc['document_type']] ?? 'Other'; ?></span>
                                        <?php echo number_format($doc['file_size'] / 1024, 1); ?> KB
                                        <?php if ($doc['description']): ?>
                                            • <?php echo htmlspecialchars($doc['description']); ?>
                                        <?php endif; ?>
                                        • <?php echo date('M j, Y', strtotime($doc['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="actions">
                                    <a href="<?php echo htmlspecialchars($doc['file_url']); ?>" target="_blank" class="btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete this document?')">
                                        <input type="hidden" name="action" value="delete" />
                                        <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>" />
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
document.getElementById('docFile').addEventListener('change', function() {
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
    var fileInput = document.getElementById('docFile');
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