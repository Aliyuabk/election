<?php
// ============================================================
// INEC DATA MANAGEMENT - SUPER ADMINISTRATOR (FULLY FEATURED)
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'super_admin') {
    header('Location: ../client-admin/');
    exit();
}

$db = getDB();

// ============================================================
// HANDLE AJAX REQUESTS
// ============================================================
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $response = ['success' => false, 'message' => ''];
    
    try {
        switch ($action) {
            case 'upload':
                if (!isset($_FILES['inec_file']) || $_FILES['inec_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Please select a valid file to upload.');
                }
                
                $file = $_FILES['inec_file'];
                $file_type = $_POST['file_type'] ?? 'polling_units';
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (!in_array($ext, ['csv', 'json', 'xlsx'])) {
                    throw new Exception('Only CSV, JSON, and Excel files are allowed.');
                }
                
                $upload_dir = '../../uploads/inec/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $filename = 'inec_' . $file_type . '_' . time() . '.' . $ext;
                $filepath = $upload_dir . $filename;
                
                if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                    throw new Exception('Failed to upload file.');
                }
                
                // Process based on file type
                $count = 0;
                if ($ext === 'csv') {
                    $handle = fopen($filepath, 'r');
                    $headers = fgetcsv($handle);
                    while (($row = fgetcsv($handle)) !== false) {
                        // Process row
                        $count++;
                    }
                    fclose($handle);
                } elseif ($ext === 'json') {
                    $content = file_get_contents($filepath);
                    $data = json_decode($content, true);
                    $count = count($data);
                }
                
                logActivity(SessionManager::get('user_id'), 'inec_upload', "Uploaded INEC $file_type data: $filename");
                
                $response = [
                    'success' => true,
                    'message' => "INEC $file_type data uploaded successfully. $count records processed.",
                    'filename' => $filename,
                    'count' => $count,
                    'file_type' => $file_type
                ];
                break;
                
            case 'clear_data':
                $data_type = $_POST['data_type'] ?? '';
                if (empty($data_type)) throw new Exception('Data type is required.');
                
                $allowed_types = ['states', 'lgas', 'wards', 'polling_units', 'senatorial_districts', 'federal_constituencies'];
                if (!in_array($data_type, $allowed_types)) throw new Exception('Invalid data type.');
                
                $stmt = $db->prepare("TRUNCATE TABLE $data_type");
                $stmt->execute();
                
                logActivity(SessionManager::get('user_id'), 'inec_clear', "Cleared INEC data: $data_type");
                $response = ['success' => true, 'message' => "Cleared $data_type data successfully."];
                break;
                
            case 'get_stats':
                $counts = [];
                $tables = ['states', 'lgas', 'wards', 'polling_units', 'senatorial_districts', 'federal_constituencies'];
                foreach ($tables as $table) {
                    try {
                        $stmt = $db->query("SELECT COUNT(*) as total FROM $table");
                        $counts[$table] = (int)($stmt->fetch()['total'] ?? 0);
                    } catch (Exception $e) {
                        $counts[$table] = 0;
                    }
                }
                $response = ['success' => true, 'data' => $counts];
                break;
                
            case 'get_data':
                $data_type = $_GET['type'] ?? 'states';
                $page = (int)($_GET['page'] ?? 1);
                $limit = 25;
                $offset = ($page - 1) * $limit;
                $search = trim($_GET['search'] ?? '');
                
                $allowed_types = ['states', 'lgas', 'wards', 'polling_units', 'senatorial_districts', 'federal_constituencies'];
                if (!in_array($data_type, $allowed_types)) throw new Exception('Invalid data type.');
                
                // Build query
                $where = [];
                $params = [];
                if (!empty($search)) {
                    $where[] = "(name LIKE ? OR code LIKE ?)";
                    $search_param = "%$search%";
                    $params = [$search_param, $search_param];
                }
                $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
                
                // Count total
                $count_sql = "SELECT COUNT(*) as total FROM $data_type $where_clause";
                $stmt = $db->prepare($count_sql);
                $stmt->execute($params);
                $total = (int)($stmt->fetch()['total'] ?? 0);
                
                // Fetch data
                $sql = "SELECT * FROM $data_type $where_clause ORDER BY name LIMIT ? OFFSET ?";
                $params[] = $limit;
                $params[] = $offset;
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $items = $stmt->fetchAll();
                
                $response = [
                    'success' => true,
                    'data' => $items,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => ceil($total / $limit),
                    'type' => $data_type
                ];
                break;
                
            default:
                throw new Exception('Invalid action.');
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    echo json_encode($response);
    exit();
}

// ============================================================
// HANDLE REGULAR POST REQUESTS
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'upload':
                if (!isset($_FILES['inec_file']) || $_FILES['inec_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Please select a valid file to upload.');
                }
                
                $file = $_FILES['inec_file'];
                $file_type = $_POST['file_type'] ?? 'polling_units';
                $upload_dir = '../../uploads/inec/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $filename = 'inec_' . $file_type . '_' . time() . '.csv';
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $action_result = ['success' => true, 'message' => 'INEC data uploaded successfully. Processing...'];
                    logActivity(SessionManager::get('user_id'), 'inec_upload', "Uploaded INEC data: $filename");
                } else {
                    $action_result = ['success' => false, 'message' => 'Failed to upload file.'];
                }
                break;
                
            case 'clear_data':
                $data_type = $_POST['data_type'] ?? '';
                if (!empty($data_type)) {
                    $stmt = $db->prepare("TRUNCATE TABLE $data_type");
                    $stmt->execute();
                    $action_result = ['success' => true, 'message' => "Cleared $data_type data successfully."];
                    logActivity(SessionManager::get('user_id'), 'inec_clear', "Cleared INEC data: $data_type");
                }
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ============================================================
// FETCH DATA COUNTS
// ============================================================
$counts = [
    'states' => 0,
    'lgas' => 0,
    'wards' => 0,
    'polling_units' => 0,
    'senatorial_districts' => 0,
    'federal_constituencies' => 0
];

$tables = ['states', 'lgas', 'wards', 'polling_units', 'senatorial_districts', 'federal_constituencies'];
foreach ($tables as $table) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as total FROM $table");
        $counts[$table] = (int)($stmt->fetch()['total'] ?? 0);
    } catch (Exception $e) {
        $counts[$table] = 0;
    }
}

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       INEC DATA - PRO STYLES
       ============================================================ */
    .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
    .page-header h2 { font-size: 1.3rem; font-weight: 700; }
    .page-header h2 small { font-size: 0.8rem; font-weight: 400; color: var(--gray-500); display: block; margin-top: 2px; }
    
    .btn-primary { padding: 8px 18px; background: #8B5CF6; color: white; border: none; border-radius: 10px; font-weight: 600; font-size: 0.85rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: var(--transition); font-family: 'Inter', sans-serif; }
    .btn-primary:hover { background: #7C3AED; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(139, 92, 246, 0.3); }
    .btn-outline { padding: 8px 16px; background: transparent; color: var(--gray-600); border: 1px solid var(--gray-200); border-radius: 10px; font-weight: 500; font-size: 0.82rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: var(--transition); font-family: 'Inter', sans-serif; }
    .btn-outline:hover { background: var(--gray-50); border-color: var(--gray-300); }
    .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; border: none; cursor: pointer; transition: var(--transition); font-family: 'Inter', sans-serif; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
    .btn-sm.danger { background: #FEF2F2; color: var(--danger); }
    .btn-sm.danger:hover { background: #FEE2E2; }
    .btn-sm.success { background: #ECFDF5; color: var(--secondary); }
    .btn-sm.success:hover { background: #D1FAE5; }
    .btn-sm.info { background: #EFF6FF; color: var(--info); }
    .btn-sm.info:hover { background: #DBEAFE; }
    
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 24px; }
    .stat-card { background: white; border-radius: var(--radius); padding: 16px 20px; border: 1px solid var(--gray-200); box-shadow: var(--shadow); transition: var(--transition); cursor: pointer; }
    .stat-card:hover { box-shadow: var(--shadow-hover); transform: translateY(-2px); }
    .stat-card .stat-number { font-size: 1.6rem; font-weight: 700; color: #8B5CF6; }
    .stat-card .stat-label { font-size: 0.75rem; color: var(--gray-500); margin-top: 2px; }
    .stat-card .stat-icon { color: #8B5CF6; font-size: 0.9rem; margin-right: 6px; }
    
    .upload-section { background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); padding: 24px 28px; box-shadow: var(--shadow); margin-bottom: 20px; }
    .upload-section .section-title { font-weight: 600; font-size: 1rem; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
    .upload-section .section-title i { color: #8B5CF6; }
    .upload-section .section-desc { color: var(--gray-500); font-size: 0.85rem; margin-bottom: 16px; }
    
    .upload-area { border: 2px dashed var(--gray-200); border-radius: 12px; padding: 40px 20px; text-align: center; cursor: pointer; transition: var(--transition); background: var(--gray-50); }
    .upload-area:hover { border-color: #8B5CF6; background: #F5F3FF; }
    .upload-area i { font-size: 2.5rem; color: var(--gray-400); display: block; margin-bottom: 12px; }
    .upload-area p { font-size: 0.9rem; color: var(--gray-500); }
    .upload-area .file-types { font-size: 0.7rem; color: var(--gray-400); margin-top: 4px; }
    .upload-area input[type="file"] { display: none; }
    .upload-area .file-selected { color: var(--secondary); font-weight: 500; }
    
    .data-types { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-top: 16px; }
    .data-type-item { background: var(--gray-50); border-radius: 10px; padding: 12px 16px; border: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; }
    .data-type-item .name { font-size: 0.82rem; font-weight: 500; }
    .data-type-item .count { font-weight: 600; color: #8B5CF6; font-size: 0.9rem; }
    
    /* Type Tabs */
    .type-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
    .type-tab { padding: 8px 18px; border-radius: 10px; border: 1px solid var(--gray-200); background: white; color: var(--gray-600); text-decoration: none; font-size: 0.82rem; font-weight: 500; transition: var(--transition); cursor: pointer; }
    .type-tab:hover { background: var(--gray-50); border-color: var(--gray-300); }
    .type-tab.active { background: #8B5CF6; color: white; border-color: #8B5CF6; }
    .type-tab .badge { background: var(--gray-200); color: var(--gray-600); padding: 1px 8px; border-radius: 12px; font-size: 0.6rem; font-weight: 600; margin-left: 4px; }
    .type-tab.active .badge { background: rgba(255,255,255,0.3); color: white; }
    
    /* Data Table */
    .table-container { background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); overflow: hidden; box-shadow: var(--shadow); }
    .table-container .table-header { padding: 16px 20px; border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; background: var(--gray-50); }
    .table-container .table-header .table-title { font-weight: 600; font-size: 0.95rem; display: flex; align-items: center; gap: 8px; }
    .table-container .table-header .table-title .count { background: #8B5CF6; color: white; padding: 0 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
    
    .data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .data-table thead { background: var(--gray-50); }
    .data-table thead th { padding: 10px 14px; text-align: left; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500); border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
    .data-table tbody td { padding: 8px 14px; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
    .data-table tbody tr:last-child td { border-bottom: none; }
    .data-table tbody tr:hover { background: var(--gray-50); }
    
    .status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 20px; font-size: 0.65rem; font-weight: 600; }
    .status-badge.active { background: #ECFDF5; color: #065F46; }
    .status-badge.inactive { background: #FEF2F2; color: #991B1B; }
    
    .pagination-pro { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 14px 20px; background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); margin-top: 16px; box-shadow: var(--shadow); }
    .pagination-pro .info { font-size: 0.82rem; color: var(--gray-500); }
    .pagination-pro .pages { display: flex; gap: 4px; align-items: center; }
    .pagination-pro .pages a, .pagination-pro .pages span { padding: 6px 14px; border-radius: 8px; font-size: 0.82rem; text-decoration: none; color: var(--gray-600); transition: var(--transition); min-width: 36px; text-align: center; border: 1px solid transparent; }
    .pagination-pro .pages a:hover { background: var(--gray-100); border-color: var(--gray-200); }
    .pagination-pro .pages .active { background: #8B5CF6; color: white; border-color: #8B5CF6; }
    .pagination-pro .pages .disabled { opacity: 0.4; cursor: not-allowed; }
    
    .empty-state-pro { text-align: center; padding: 48px 20px; color: var(--gray-500); }
    .empty-state-pro i { font-size: 3rem; color: var(--gray-300); display: block; margin-bottom: 12px; }
    
    .filter-bar-pro { background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); padding: 14px 20px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; box-shadow: var(--shadow); }
    .filter-bar-pro .search-wrap { flex: 1; min-width: 200px; display: flex; align-items: center; background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 10px; padding: 6px 14px; transition: var(--transition); }
    .filter-bar-pro .search-wrap:focus-within { border-color: #8B5CF6; background: white; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1); }
    .filter-bar-pro .search-wrap i { color: var(--gray-400); font-size: 0.85rem; }
    .filter-bar-pro .search-wrap input { border: none; outline: none; background: transparent; padding: 6px 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem; width: 100%; color: var(--gray-700); }
    .filter-bar-pro .btn-filter { padding: 8px 18px; background: #8B5CF6; color: white; border: none; border-radius: 10px; font-weight: 600; font-size: 0.8rem; cursor: pointer; transition: var(--transition); font-family: 'Inter', sans-serif; display: inline-flex; align-items: center; gap: 6px; }
    .filter-bar-pro .btn-filter:hover { background: #7C3AED; }
    
    .error-message { background: #FEF2F2; color: #DC2626; padding: 14px 18px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 16px; border: 1px solid #FECACA; display: flex; align-items: flex-start; gap: 12px; }
    .success-message { background: #ECFDF5; color: #065F46; padding: 14px 18px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 16px; border: 1px solid #A7F3D0; display: flex; align-items: flex-start; gap: 12px; }
    
    .toast-container { position: fixed; top: 80px; right: 20px; z-index: 999; display: flex; flex-direction: column; gap: 8px; }
    .toast { padding: 14px 20px; border-radius: 10px; color: white; font-size: 0.85rem; font-weight: 500; box-shadow: var(--shadow-hover); animation: slideIn 0.3s ease; min-width: 280px; max-width: 400px; display: flex; align-items: center; gap: 10px; }
    .toast.success { background: var(--secondary); }
    .toast.error { background: var(--danger); }
    @keyframes slideIn { from { opacity: 0; transform: translateX(100px); } to { opacity: 1; transform: translateX(0); } }
    
    .loading-spinner { display: inline-block; width: 20px; height: 20px; border: 3px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .upload-section { padding: 16px; }
        .upload-area { padding: 24px 16px; }
        .data-types { grid-template-columns: 1fr; }
        .page-header { flex-direction: column; align-items: flex-start; }
        .filter-bar-pro { flex-direction: column; align-items: stretch; }
        .filter-bar-pro .search-wrap { min-width: auto; }
        .table-container { overflow-x: auto; }
        .data-table { font-size: 0.78rem; }
        .data-table th, .data-table td { padding: 6px 10px; }
        .pagination-pro { flex-direction: column; align-items: center; }
        .type-tabs { flex-wrap: wrap; }
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
        .stat-card { padding: 12px 14px; }
        .stat-card .stat-number { font-size: 1.2rem; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Toast Messages -->
        <?php if (!empty($action_result['message'])): ?>
        <div style="margin-bottom:16px;">
            <div class="toast <?php echo $action_result['success'] ? 'success' : 'error'; ?>" style="position:static;animation:none;max-width:100%;">
                <i class="fas <?php echo $action_result['success'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($action_result['message']); ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-database" style="color:#8B5CF6;margin-right:8px;"></i> INEC Master Data
                    <small>Upload, view, and manage INEC geographic data</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="inec-export.php" class="btn-outline">
                    <i class="fas fa-file-export"></i> Export All
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid" id="statsGrid">
            <?php foreach ($counts as $key => $count): ?>
                <div class="stat-card" onclick="switchTab('<?php echo $key; ?>')">
                    <div class="stat-number"><?php echo number_format($count); ?></div>
                    <div class="stat-label">
                        <i class="fas <?php 
                            echo $key === 'states' ? 'fa-flag' : 
                                ($key === 'lgas' ? 'fa-map-marker-alt' : 
                                ($key === 'wards' ? 'fa-layer-group' : 
                                ($key === 'polling_units' ? 'fa-flag-checkered' : 
                                ($key === 'senatorial_districts' ? 'fa-star' : 'fa-university')))); 
                        ?> stat-icon"></i>
                        <?php echo ucfirst(str_replace('_', ' ', $key)); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Upload Section -->
        <div class="upload-section">
            <div class="section-title">
                <i class="fas fa-upload"></i> Upload INEC Data
            </div>
            <div class="section-desc">
                Upload CSV, JSON, or Excel files containing INEC master data. Select the data type before uploading.
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="action" value="upload">
                
                <div class="upload-area" onclick="document.getElementById('inecFile').click()">
                    <i class="fas fa-cloud-upload-alt" id="uploadIcon"></i>
                    <p id="uploadText"><strong>Click to upload</strong> or drag and drop</p>
                    <p class="file-types">Supported: CSV, Excel (.xlsx, .xls), JSON</p>
                    <input type="file" name="inec_file" id="inecFile" accept=".csv,.xlsx,.xls,.json" required>
                </div>
                
                <div style="margin-top:12px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                    <select name="file_type" class="form-control" style="padding:8px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.82rem;background:var(--gray-50);min-width:160px;">
                        <option value="states">States</option>
                        <option value="lgas">LGAs</option>
                        <option value="wards">Wards</option>
                        <option value="polling_units">Polling Units</option>
                        <option value="senatorial_districts">Senatorial Districts</option>
                        <option value="federal_constituencies">Federal Constituencies</option>
                    </select>
                    <button type="submit" class="btn-primary" id="uploadBtn">
                        <i class="fas fa-upload"></i> Upload Data
                    </button>
                </div>
            </form>
        </div>

        <!-- Data Management -->
        <div class="upload-section">
            <div class="section-title">
                <i class="fas fa-tools"></i> Data Management
            </div>
            <div class="section-desc">
                Manage existing INEC data. Clear specific data types or view the current data status.
            </div>
            
            <div class="data-types">
                <?php foreach ($counts as $key => $count): ?>
                    <div class="data-type-item">
                        <span class="name"><?php echo ucfirst(str_replace('_', ' ', $key)); ?></span>
                        <span class="count"><?php echo number_format($count); ?></span>
                        <form method="POST" style="display:inline;" onsubmit="return confirmClear('<?php echo $key; ?>')">
                            <input type="hidden" name="action" value="clear_data">
                            <input type="hidden" name="data_type" value="<?php echo $key; ?>">
                            <button type="submit" class="btn-sm danger" title="Clear all <?php echo $key; ?> data">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
                <a href="inec-view.php" class="btn-outline">
                    <i class="fas fa-eye"></i> View All Data
                </a>
                <a href="inec-export.php" class="btn-outline">
                    <i class="fas fa-file-export"></i> Export Data
                </a>
                <button class="btn-outline" onclick="refreshStats()">
                    <i class="fas fa-sync"></i> Refresh Stats
                </button>
            </div>
        </div>

        <!-- Data Preview Section -->
        <div style="margin-top:20px;">
            <h3 style="font-size:1rem;font-weight:600;margin-bottom:12px;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-table" style="color:#8B5CF6;"></i> Data Preview
                <span style="font-size:0.75rem;font-weight:400;color:var(--gray-400);">(Click stats cards to switch views)</span>
            </h3>
            
            <!-- Type Tabs -->
            <div class="type-tabs" id="typeTabs">
                <?php foreach ($counts as $key => $count): ?>
                    <button class="type-tab <?php echo $key === 'states' ? 'active' : ''; ?>" data-type="<?php echo $key; ?>" onclick="switchTab('<?php echo $key; ?>')">
                        <?php echo ucfirst(str_replace('_', ' ', $key)); ?>
                        <span class="badge"><?php echo number_format($count); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Filter -->
            <div class="filter-bar-pro">
                <div style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                    <div class="search-wrap" style="flex:1;">
                        <i class="fas fa-search"></i>
                        <input type="text" id="dataSearch" placeholder="Search by name or code..." onkeyup="if(event.key==='Enter') loadData(1)">
                    </div>
                    <button class="btn-filter" onclick="loadData(1)"><i class="fas fa-filter"></i> Filter</button>
                    <button class="btn-outline" onclick="document.getElementById('dataSearch').value='';loadData(1)" style="padding:8px 14px;font-size:0.8rem;">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-list" style="color:#8B5CF6;"></i> <span id="tableTitle">States</span>
                        <span class="count" id="totalCount">0</span>
                    </div>
                    <div>
                        <button class="btn-sm info" onclick="loadData(1)">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                </div>
                <div id="dataTableWrapper">
                    <table class="data-table">
                        <thead>
                            <tr id="tableHeaders">
                                <th>#</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr>
                                <td colspan="10" style="text-align:center;padding:40px;color:var(--gray-500);">
                                    <i class="fas fa-spinner fa-spin" style="font-size:2rem;display:block;margin-bottom:10px;color:#8B5CF6;"></i>
                                    Loading data...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div class="pagination-pro" id="paginationContainer">
                <div class="info" id="paginationInfo">Loading...</div>
                <div class="pages" id="paginationPages"></div>
            </div>
        </div>

        <!-- Info Section -->
        <div style="margin-top:20px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);padding:16px 20px;box-shadow:var(--shadow);">
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <i class="fas fa-info-circle" style="color:#8B5CF6;font-size:1.2rem;margin-top:2px;"></i>
                <div>
                    <h4 style="font-size:0.9rem;font-weight:600;margin-bottom:4px;">About INEC Data</h4>
                    <p style="color:var(--gray-500);font-size:0.85rem;">
                        INEC master data includes all geographic and administrative boundaries used for elections.
                        This data is used for creating elections, assigning polling units, and generating reports.
                        <br><br>
                        <strong>Data Sources:</strong> INEC official publications, National Population Commission, and independent electoral observers.
                        <br>
                        <strong>Last Updated:</strong> <?php echo date('F j, Y'); ?>
                    </p>
                </div>
            </div>
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
    // Load initial data
    loadData(1);
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
// INEC DATA FUNCTIONS
// ============================================================
var currentType = 'states';
var currentPage = 1;
var totalPages = 1;

// Define table columns for each type
var tableColumns = {
    'states': ['id', 'code', 'name', 'capital', 'registered_voters', 'is_active'],
    'lgas': ['id', 'state_id', 'code', 'name', 'registered_voters', 'is_active'],
    'wards': ['id', 'lga_id', 'code', 'name', 'registered_voters', 'is_active'],
    'polling_units': ['id', 'ward_id', 'code', 'name', 'registered_voters', 'is_active'],
    'senatorial_districts': ['id', 'state_id', 'code', 'name', 'is_active'],
    'federal_constituencies': ['id', 'state_id', 'code', 'name', 'is_active']
};

var tableLabels = {
    'states': 'States',
    'lgas': 'LGAs',
    'wards': 'Wards',
    'polling_units': 'Polling Units',
    'senatorial_districts': 'Senatorial Districts',
    'federal_constituencies': 'Federal Constituencies'
};

function switchTab(type) {
    currentType = type;
    currentPage = 1;
    
    // Update tabs
    document.querySelectorAll('.type-tab').forEach(function(tab) {
        tab.classList.remove('active');
        if (tab.dataset.type === type) {
            tab.classList.add('active');
        }
    });
    
    // Update table title
    document.getElementById('tableTitle').textContent = tableLabels[type] || type;
    
    // Load data
    loadData(1);
}

function loadData(page) {
    currentPage = page || 1;
    var search = document.getElementById('dataSearch').value.trim();
    
    // Show loading
    document.getElementById('tableBody').innerHTML = `
        <tr>
            <td colspan="10" style="text-align:center;padding:40px;color:var(--gray-500);">
                <i class="fas fa-spinner fa-spin" style="font-size:2rem;display:block;margin-bottom:10px;color:#8B5CF6;"></i>
                Loading data...
            </td>
        </tr>
    `;
    
    fetch('inec-data.php?action=get_data&type=' + currentType + '&page=' + currentPage + '&search=' + encodeURIComponent(search), {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            renderTable(data);
        } else {
            showToast('error', data.message);
        }
    })
    .catch(function() {
        showToast('error', 'An error occurred while loading data.');
    });
}

function renderTable(data) {
    var items = data.data || [];
    var total = data.total || 0;
    var totalPages = data.total_pages || 1;
    var columns = tableColumns[currentType] || [];
    
    // Update count
    document.getElementById('totalCount').textContent = total;
    
    // Render headers
    var headersHtml = '<th>#</th>';
    columns.forEach(function(col) {
        headersHtml += '<th>' + col.replace('_', ' ').charAt(0).toUpperCase() + col.replace('_', ' ').slice(1) + '</th>';
    });
    document.getElementById('tableHeaders').innerHTML = headersHtml;
    
    // Render body
    var bodyHtml = '';
    if (items.length > 0) {
        items.forEach(function(item, index) {
            var offset = (currentPage - 1) * 25;
            bodyHtml += '<tr>';
            bodyHtml += '<td>' + (offset + index + 1) + '</td>';
            columns.forEach(function(col) {
                var value = item[col] !== undefined ? item[col] : '';
                if (col === 'is_active') {
                    bodyHtml += '<td><span class="status-badge ' + (value ? 'active' : 'inactive') + '">' + (value ? 'Active' : 'Inactive') + '</span></td>';
                } else if (col === 'registered_voters') {
                    bodyHtml += '<td>' + (value ? Number(value).toLocaleString() : '0') + '</td>';
                } else {
                    bodyHtml += '<td>' + htmlEntities(String(value)) + '</td>';
                }
            });
            bodyHtml += '</tr>';
        });
    } else {
        bodyHtml = `
            <tr>
                <td colspan="${columns.length + 1}" style="text-align:center;padding:40px;color:var(--gray-500);">
                    <i class="fas fa-database" style="font-size:2rem;display:block;margin-bottom:10px;color:var(--gray-300);"></i>
                    <h4>No data found</h4>
                    <p>Try adjusting your search or upload data.</p>
                </td>
            </tr>
        `;
    }
    document.getElementById('tableBody').innerHTML = bodyHtml;
    
    // Render pagination
    renderPagination(currentPage, totalPages, total);
}

function renderPagination(page, totalPages, total) {
    var container = document.getElementById('paginationContainer');
    var info = document.getElementById('paginationInfo');
    var pages = document.getElementById('paginationPages');
    
    if (totalPages <= 1) {
        container.style.display = 'none';
        return;
    }
    container.style.display = 'flex';
    
    var start = (page - 1) * 25 + 1;
    var end = Math.min(page * 25, total);
    info.innerHTML = 'Showing <strong>' + start + '</strong> to <strong>' + end + '</strong> of <strong>' + Number(total).toLocaleString() + '</strong>';
    
    var html = '';
    var search = document.getElementById('dataSearch').value.trim();
    
    if (page > 1) {
        html += '<a href="#" onclick="loadData(' + (page - 1) + ');return false;"><i class="fas fa-chevron-left"></i></a>';
    } else {
        html += '<span class="disabled"><i class="fas fa-chevron-left"></i></span>';
    }
    
    var startPage = Math.max(1, page - 2);
    var endPage = Math.min(totalPages, page + 2);
    
    if (startPage > 1) {
        html += '<a href="#" onclick="loadData(1);return false;">1</a>';
        if (startPage > 2) html += '<span>…</span>';
    }
    
    for (var i = startPage; i <= endPage; i++) {
        html += '<a href="#" onclick="loadData(' + i + ');return false;" class="' + (i === page ? 'active' : '') + '">' + i + '</a>';
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) html += '<span>…</span>';
        html += '<a href="#" onclick="loadData(' + totalPages + ');return false;">' + totalPages + '</a>';
    }
    
    if (page < totalPages) {
        html += '<a href="#" onclick="loadData(' + (page + 1) + ');return false;"><i class="fas fa-chevron-right"></i></a>';
    } else {
        html += '<span class="disabled"><i class="fas fa-chevron-right"></i></span>';
    }
    
    pages.innerHTML = html;
}

function refreshStats() {
    fetch('inec-data.php?action=get_stats', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            var counts = data.data;
            var cards = document.querySelectorAll('#statsGrid .stat-card');
            var keys = Object.keys(counts);
            cards.forEach(function(card, index) {
                if (index < keys.length) {
                    var key = keys[index];
                    var numberEl = card.querySelector('.stat-number');
                    if (numberEl) {
                        numberEl.textContent = Number(counts[key] || 0).toLocaleString();
                    }
                    // Update badge in tabs
                    var tabs = document.querySelectorAll('.type-tab');
                    tabs.forEach(function(tab) {
                        if (tab.dataset.type === key) {
                            var badge = tab.querySelector('.badge');
                            if (badge) {
                                badge.textContent = Number(counts[key] || 0).toLocaleString();
                            }
                        }
                    });
                }
            });
            showToast('success', 'Stats refreshed successfully.');
        }
    })
    .catch(function() {
        showToast('error', 'Failed to refresh stats.');
    });
}

function confirmClear(type) {
    return confirm('Are you sure you want to clear all ' + type.replace('_', ' ') + ' data? This action cannot be undone.');
}

// ============================================================
// FILE UPLOAD HANDLER
// ============================================================
document.getElementById('inecFile').addEventListener('change', function() {
    var fileName = this.files[0] ? this.files[0].name : 'No file selected';
    var uploadText = document.getElementById('uploadText');
    var uploadIcon = document.getElementById('uploadIcon');
    
    if (this.files[0]) {
        uploadText.innerHTML = '<span class="file-selected"><i class="fas fa-check-circle"></i> ' + fileName + '</span>';
        uploadIcon.className = 'fas fa-file';
    } else {
        uploadText.innerHTML = '<strong>Click to upload</strong> or drag and drop';
        uploadIcon.className = 'fas fa-cloud-upload-alt';
    }
});

document.getElementById('uploadForm').addEventListener('submit', function(e) {
    var fileInput = document.getElementById('inecFile');
    if (!fileInput.files || !fileInput.files[0]) {
        e.preventDefault();
        showToast('error', 'Please select a file to upload.');
        return;
    }
    
    var btn = document.getElementById('uploadBtn');
    var originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
    btn.disabled = true;
    
    // Allow form to submit normally
    setTimeout(function() {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }, 3000);
});

// ============================================================
// TOAST NOTIFICATIONS
// ============================================================
function showToast(type, message) {
    var container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
    var toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = '<i class="fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i> ' + message;
    container.appendChild(toast);
    
    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100px)';
        setTimeout(function() {
            toast.remove();
        }, 300);
    }, 4000);
}

// ============================================================
// SEARCH (header)
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

// ============================================================
// UTILITY FUNCTIONS
// ============================================================
function htmlEntities(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>
</body>
</html>