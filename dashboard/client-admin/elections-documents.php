<?php
// ============================================================
// ELECTION DOCUMENTS - CLIENT ADMIN (PROFESSIONAL UI)
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
// GET ELECTION ID
// ============================================================
$election_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($election_id <= 0) {
    header('Location: elections.php');
    exit();
}

// ============================================================
// FETCH ELECTION DETAILS
// ============================================================
$election = null;
try {
    $stmt = $db->prepare("SELECT * FROM elections WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL");
    $stmt->execute([$election_id, $tenant_id]);
    $election = $stmt->fetch();
} catch (Exception $e) {}

if (!$election) {
    header('Location: elections.php');
    exit();
}

// ============================================================
// HANDLE ACTIONS
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'upload_document':
                $doc_type = trim($_POST['doc_type'] ?? '');
                $doc_name = trim($_POST['doc_name'] ?? '');
                $doc_description = trim($_POST['doc_description'] ?? '');
                
                if (empty($doc_type) || empty($doc_name)) {
                    throw new Exception('Document type and name are required.');
                }
                
                // In production, handle file upload here
                $action_result = ['success' => true, 'message' => 'Document uploaded successfully.'];
                break;
                
            case 'delete_document':
                $doc_id = (int)($_POST['doc_id'] ?? 0);
                if ($doc_id <= 0) throw new Exception('Invalid document ID.');
                
                // In production, delete file and database record
                $action_result = ['success' => true, 'message' => 'Document deleted successfully.'];
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ============================================================
// SAMPLE DOCUMENTS (In production, fetch from database)
// ============================================================
$documents = [
    [
        'id' => 1,
        'name' => 'Election_Guidelines_2027.pdf',
        'type' => 'guidelines',
        'size' => '1.2 MB',
        'date' => '2024-01-15',
        'uploaded_by' => 'Admin User',
        'description' => 'Official election guidelines for all stakeholders'
    ],
    [
        'id' => 2,
        'name' => 'Candidate_List_Official.xlsx',
        'type' => 'candidate_list',
        'size' => '856 KB',
        'date' => '2024-01-20',
        'uploaded_by' => 'Admin User',
        'description' => 'Complete list of all candidates running in this election'
    ],
    [
        'id' => 3,
        'name' => 'Election_Rules_Regulations.docx',
        'type' => 'rules',
        'size' => '2.4 MB',
        'date' => '2024-01-10',
        'uploaded_by' => 'System',
        'description' => 'Comprehensive rules and regulations for the election'
    ],
    [
        'id' => 4,
        'name' => 'Official_Notice_Election_Date.pdf',
        'type' => 'notice',
        'size' => '345 KB',
        'date' => '2024-01-25',
        'uploaded_by' => 'Admin User',
        'description' => 'Official notice of election date and procedures'
    ],
    [
        'id' => 5,
        'name' => 'Voter_Registration_Guide.pdf',
        'type' => 'voter_guide',
        'size' => '678 KB',
        'date' => '2024-02-01',
        'uploaded_by' => 'Admin User',
        'description' => 'Step-by-step guide for voter registration'
    ],
    [
        'id' => 6,
        'name' => 'Polling_Unit_Results_2027.xlsx',
        'type' => 'result_sheet',
        'size' => '1.8 MB',
        'date' => '2024-02-05',
        'uploaded_by' => 'System',
        'description' => 'Results from all polling units'
    ],
];

$doc_types = [
    'guidelines' => ['label' => 'Guidelines', 'icon' => 'fa-file-pdf', 'color' => '#DC2626'],
    'candidate_list' => ['label' => 'Candidate List', 'icon' => 'fa-file-excel', 'color' => '#10B981'],
    'rules' => ['label' => 'Rules', 'icon' => 'fa-file-word', 'color' => '#2563EB'],
    'notice' => ['label' => 'Notice', 'icon' => 'fa-file-pdf', 'color' => '#DC2626'],
    'voter_guide' => ['label' => 'Voter Guide', 'icon' => 'fa-file-pdf', 'color' => '#DC2626'],
    'result_sheet' => ['label' => 'Result Sheet', 'icon' => 'fa-file-excel', 'color' => '#10B981'],
    'other' => ['label' => 'Other', 'icon' => 'fa-file-alt', 'color' => '#64748B'],
];

$document_icons = [
    'pdf' => ['icon' => 'fa-file-pdf', 'color' => '#DC2626', 'bg' => '#FEF2F2'],
    'word' => ['icon' => 'fa-file-word', 'color' => '#2563EB', 'bg' => '#EFF6FF'],
    'excel' => ['icon' => 'fa-file-excel', 'color' => '#10B981', 'bg' => '#ECFDF5'],
    'text' => ['icon' => 'fa-file-alt', 'color' => '#7C3AED', 'bg' => '#F5F3FF'],
    'image' => ['icon' => 'fa-file-image', 'color' => '#F59E0B', 'bg' => '#FFFBEB'],
];

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       ELECTION DOCUMENTS - PROFESSIONAL UI STYLES
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
    
    .btn-primary {
        padding: 10px 20px;
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
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.3);
    }
    .btn-outline {
        padding: 10px 18px;
        background: transparent;
        color: var(--gray-600);
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-outline:hover {
        background: var(--gray-50);
        border-color: var(--primary);
        color: var(--primary);
    }
    .btn-sm {
        padding: 4px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-sm.info { background: #EFF6FF; color: #1E40AF; }
    .btn-sm.info:hover { background: #DBEAFE; }
    .btn-sm.danger { background: #FEF2F2; color: #991B1B; }
    .btn-sm.danger:hover { background: #FEE2E2; }
    
    .doc-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 14px;
        margin-bottom: 24px;
    }
    .stat-card {
        background: white;
        border-radius: 14px;
        padding: 16px 20px;
        border: 1px solid var(--gray-200);
        text-align: center;
        transition: var(--transition);
    }
    .stat-card:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-2px);
    }
    .stat-card .number {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--primary);
        line-height: 1.2;
    }
    .stat-card .number.purple { color: #8B5CF6; }
    .stat-card .label {
        font-size: 0.75rem;
        color: var(--gray-500);
        margin-top: 4px;
        font-weight: 500;
    }
    
    .table-container {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        overflow: hidden;
        box-shadow: var(--shadow);
    }
    .table-container .table-header {
        padding: 16px 24px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        background: linear-gradient(135deg, var(--gray-50), white);
    }
    .table-container .table-header .table-title {
        font-weight: 600;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--gray-700);
    }
    .table-container .table-header .table-title i {
        color: var(--primary);
    }
    .table-container .table-header .table-title .count {
        background: var(--primary);
        color: white;
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .table-container .table-header .table-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    .data-table thead {
        background: var(--gray-50);
    }
    .data-table thead th {
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--gray-500);
        border-bottom: 2px solid var(--gray-200);
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 2;
        background: var(--gray-50);
    }
    .data-table tbody td {
        padding: 10px 16px;
        border-bottom: 1px solid var(--gray-100);
        vertical-align: middle;
    }
    .data-table tbody tr:hover {
        background: var(--gray-50);
    }
    
    .doc-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    .doc-icon.pdf { background: #FEF2F2; color: #DC2626; }
    .doc-icon.word { background: #EFF6FF; color: #2563EB; }
    .doc-icon.excel { background: #ECFDF5; color: #10B981; }
    .doc-icon.text { background: #F5F3FF; color: #7C3AED; }
    .doc-icon.image { background: #FFFBEB; color: #F59E0B; }
    .doc-icon.other { background: var(--gray-100); color: var(--gray-500); }
    
    .doc-name {
        font-weight: 500;
        font-size: 0.85rem;
        color: var(--gray-700);
    }
    .doc-meta {
        font-size: 0.7rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .doc-meta .doc-type {
        background: var(--gray-100);
        padding: 1px 10px;
        border-radius: 10px;
        font-size: 0.6rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--gray-500);
    }
    .empty-state i {
        font-size: 4rem;
        color: var(--gray-300);
        display: block;
        margin-bottom: 16px;
    }
    .empty-state h4 {
        color: var(--gray-700);
        margin-bottom: 8px;
        font-size: 1.1rem;
    }
    .empty-state p {
        font-size: 0.9rem;
        color: var(--gray-400);
        max-width: 400px;
        margin: 0 auto;
    }
    
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(8px);
        z-index: 300;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .modal-overlay.active { display: flex; }
    .modal {
        background: white;
        border-radius: var(--radius);
        max-width: 540px;
        width: 100%;
        padding: 28px 32px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: modalIn 0.3s ease;
        max-height: 90vh;
        overflow-y: auto;
    }
    @keyframes modalIn {
        from { opacity: 0; transform: scale(0.95) translateY(20px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
    .modal .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 14px;
        border-bottom: 2px solid var(--gray-100);
    }
    .modal .modal-header h3 {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--gray-800);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .modal .modal-header h3 i {
        color: var(--primary);
    }
    .modal .modal-header .close-btn {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: var(--gray-400);
        cursor: pointer;
        transition: var(--transition);
        padding: 4px 8px;
        border-radius: 8px;
    }
    .modal .modal-header .close-btn:hover {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .modal .form-group {
        margin-bottom: 16px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .modal .form-group label {
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--gray-700);
    }
    .modal .form-group label .required {
        color: var(--danger);
        margin-left: 2px;
    }
    .modal .form-group .help-text {
        font-size: 0.75rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .modal .form-group select,
    .modal .form-group input,
    .modal .form-group textarea {
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
    .modal .form-group select:focus,
    .modal .form-group input:focus,
    .modal .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.08);
    }
    .modal .form-group textarea {
        resize: vertical;
        min-height: 60px;
    }
    .modal .file-upload-area {
        border: 2px dashed var(--gray-200);
        border-radius: 10px;
        padding: 30px 20px;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        background: var(--gray-50);
    }
    .modal .file-upload-area:hover {
        border-color: var(--primary);
        background: #EFF6FF;
    }
    .modal .file-upload-area i {
        font-size: 2.5rem;
        color: var(--gray-400);
        display: block;
        margin-bottom: 10px;
    }
    .modal .file-upload-area p {
        font-size: 0.9rem;
        color: var(--gray-500);
        margin-bottom: 4px;
    }
    .modal .file-upload-area .file-types {
        font-size: 0.7rem;
        color: var(--gray-400);
    }
    .modal .file-upload-area input[type="file"] {
        display: none;
    }
    .modal .file-preview {
        display: none;
        margin-top: 12px;
        padding: 12px 16px;
        background: var(--gray-50);
        border-radius: 8px;
        border: 1px solid var(--gray-200);
        text-align: left;
    }
    .modal .file-preview.show {
        display: block;
    }
    .modal .file-preview .file-name {
        font-weight: 500;
        font-size: 0.85rem;
        color: var(--gray-700);
    }
    .modal .file-preview .file-size {
        font-size: 0.7rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .modal .modal-footer {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 2px solid var(--gray-100);
    }
    .modal .modal-footer .btn {
        padding: 10px 24px;
        border-radius: 10px;
        border: none;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .modal .modal-footer .btn-primary {
        background: var(--primary);
        color: white;
    }
    .modal .modal-footer .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
    }
    .modal .modal-footer .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .modal .modal-footer .btn-secondary:hover {
        background: var(--gray-200);
    }
    
    .toast {
        padding: 14px 20px;
        border-radius: 10px;
        color: white;
        font-size: 0.85rem;
        font-weight: 500;
        box-shadow: var(--shadow-hover);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .toast.success { background: var(--secondary); }
    .toast.error { background: var(--danger); }
    
    @media (max-width: 768px) {
        .doc-stats {
            grid-template-columns: repeat(2, 1fr);
        }
        .table-container {
            overflow-x: auto;
        }
        .data-table {
            font-size: 0.78rem;
        }
        .data-table th, .data-table td {
            padding: 8px 12px;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .modal {
            padding: 20px;
            margin: 10px;
        }
        .modal .modal-footer {
            flex-direction: column;
        }
        .modal .modal-footer .btn {
            width: 100%;
            justify-content: center;
        }
    }
    @media (max-width: 480px) {
        .doc-stats {
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .stat-card {
            padding: 12px 14px;
        }
        .stat-card .number {
            font-size: 1.3rem;
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
                    <i class="fas fa-file-alt" style="color:var(--primary);margin-right:8px;"></i> Election Documents
                    <small><?php echo htmlspecialchars($election['name']); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button onclick="openModal('uploadDocModal')" class="btn-primary">
                    <i class="fas fa-upload"></i> Upload Document
                </button>
                <a href="elections-view.php?id=<?php echo $election_id; ?>" class="btn-outline">
                    <i class="fas fa-eye"></i> View
                </a>
                <a href="elections.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Toast Messages -->
        <?php if (!empty($action_result['message'])): ?>
        <div class="toast <?php echo $action_result['success'] ? 'success' : 'error'; ?>">
            <i class="fas <?php echo $action_result['success'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($action_result['message']); ?>
        </div>
        <?php endif; ?>

        <!-- Document Stats -->
        <div class="doc-stats">
            <div class="stat-card">
                <div class="number"><?php echo count($documents); ?></div>
                <div class="label">Total Documents</div>
            </div>
            <div class="stat-card">
                <div class="number purple"><?php 
                    $pdf_count = 0;
                    foreach ($documents as $doc) {
                        $ext = pathinfo($doc['name'], PATHINFO_EXTENSION);
                        if (in_array($ext, ['pdf', 'doc', 'docx'])) $pdf_count++;
                    }
                    echo $pdf_count;
                ?></div>
                <div class="label">PDF/Word Files</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color:#10B981;"><?php 
                    $excel_count = 0;
                    foreach ($documents as $doc) {
                        $ext = pathinfo($doc['name'], PATHINFO_EXTENSION);
                        if (in_array($ext, ['xls', 'xlsx', 'csv'])) $excel_count++;
                    }
                    echo $excel_count;
                ?></div>
                <div class="label">Excel/CSV Files</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color:#F59E0B;"><?php 
                    $total_size = 0;
                    foreach ($documents as $doc) {
                        $size = str_replace([' MB', ' KB'], '', $doc['size']);
                        if (strpos($doc['size'], 'MB') !== false) $total_size += floatval($size);
                        else $total_size += floatval($size) / 1024;
                    }
                    echo round($total_size, 1);
                ?> MB</div>
                <div class="label">Total Storage Used</div>
            </div>
        </div>

        <!-- Documents Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list" style="color:var(--primary);"></i> Documents
                    <span class="count"><?php echo count($documents); ?></span>
                </div>
                <div class="table-actions">
                    <span style="font-size:0.75rem;color:var(--gray-400);">
                        Showing <?php echo count($documents); ?> documents
                    </span>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>File</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Uploaded</th>
                        <th>Uploaded By</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($documents) > 0): ?>
                        <?php $index = 1; foreach ($documents as $doc): 
                            $ext = pathinfo($doc['name'], PATHINFO_EXTENSION);
                            $icon_class = 'other';
                            $icon_name = 'fa-file-alt';
                            if (in_array($ext, ['pdf'])) { $icon_class = 'pdf'; $icon_name = 'fa-file-pdf'; }
                            elseif (in_array($ext, ['doc', 'docx'])) { $icon_class = 'word'; $icon_name = 'fa-file-word'; }
                            elseif (in_array($ext, ['xls', 'xlsx'])) { $icon_class = 'excel'; $icon_name = 'fa-file-excel'; }
                            elseif (in_array($ext, ['txt'])) { $icon_class = 'text'; $icon_name = 'fa-file-alt'; }
                            elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) { $icon_class = 'image'; $icon_name = 'fa-file-image'; }
                            
                            $type_label = $doc_types[$doc['type']]['label'] ?? ucfirst($doc['type']);
                        ?>
                            <tr>
                                <td><?php echo $index++; ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:12px;">
                                        <div class="doc-icon <?php echo $icon_class; ?>">
                                            <i class="fas <?php echo $icon_name; ?>"></i>
                                        </div>
                                        <div>
                                            <div class="doc-name"><?php echo htmlspecialchars($doc['name']); ?></div>
                                            <?php if (!empty($doc['description'])): ?>
                                                <div style="font-size:0.7rem;color:var(--gray-400);"><?php echo htmlspecialchars($doc['description']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="doc-type" style="background:var(--gray-100);padding:2px 10px;border-radius:10px;font-size:0.6rem;font-weight:600;text-transform:uppercase;letter-spacing:0.03em;">
                                        <?php echo $type_label; ?>
                                    </span>
                                </td>
                                <td><?php echo $doc['size']; ?></td>
                                <td><?php echo date('M j, Y', strtotime($doc['date'])); ?></td>
                                <td><?php echo htmlspecialchars($doc['uploaded_by']); ?></td>
                                <td style="text-align:center;">
                                    <button class="btn-sm info" onclick="alert('Downloading <?php echo $doc['name']; ?>')">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button class="btn-sm danger" onclick="if(confirm('Delete this document?')){alert('Document deleted');}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-file-alt"></i>
                                    <h4>No documents uploaded</h4>
                                    <p>Upload documents related to this election for easy access.</p>
                                    <button onclick="openModal('uploadDocModal')" class="btn-primary" style="margin-top:12px;">
                                        <i class="fas fa-upload"></i> Upload First Document
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Upload Document Modal -->
<div class="modal-overlay" id="uploadDocModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-upload" style="color:var(--primary);"></i> Upload Document</h3>
            <button class="close-btn" onclick="closeModal('uploadDocModal')">&times;</button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_document">
            <input type="hidden" name="election_id" value="<?php echo $election_id; ?>">
            
            <div class="form-group">
                <label>Document Type <span class="required">*</span></label>
                <select name="doc_type" required>
                    <option value="">Select Document Type</option>
                    <option value="guidelines">Election Guidelines</option>
                    <option value="candidate_list">Candidate List</option>
                    <option value="rules">Rules &amp; Regulations</option>
                    <option value="notice">Official Notice</option>
                    <option value="voter_guide">Voter Guide</option>
                    <option value="result_sheet">Result Sheet</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Document Name <span class="required">*</span></label>
                <input type="text" name="doc_name" placeholder="e.g., Election Guidelines 2027" required>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="doc_description" placeholder="Brief description of the document" rows="2"></textarea>
            </div>
            
            <div class="form-group">
                <label>File <span class="required">*</span></label>
                <div class="file-upload-area" onclick="document.getElementById('docFile').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Click to upload or drag &amp; drop</p>
                    <div class="file-types">Supported: PDF, DOC, DOCX, XLS, XLSX, TXT (Max 10MB)</div>
                    <input type="file" name="document" id="docFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt" required>
                </div>
                <div class="file-preview" id="docPreview">
                    <div class="file-name" id="docFileName">file.pdf</div>
                    <div class="file-size" id="docFileSize">0 KB</div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('uploadDocModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Upload Document
                </button>
            </div>
        </form>
    </div>
</div>

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
// MODAL FUNCTIONS
// ============================================================
function openModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = '';
}

document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});

// ============================================================
// FILE UPLOAD PREVIEW
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var fileInput = document.getElementById('docFile');
    var preview = document.getElementById('docPreview');
    var fileName = document.getElementById('docFileName');
    var fileSize = document.getElementById('docFileSize');
    
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                var file = this.files[0];
                fileName.textContent = file.name;
                fileSize.textContent = (file.size / 1024).toFixed(1) + ' KB';
                preview.classList.add('show');
            } else {
                preview.classList.remove('show');
            }
        });
    }
});

// ============================================================
// SEARCH FUNCTIONALITY
// ============================================================
var searchInput = document.querySelector('.search-wrap input');
if (searchInput) {
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            this.closest('form').submit();
        }
    });
}
</script>
</body>
</html>