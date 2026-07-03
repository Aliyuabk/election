<?php
// ============================================================
// BACKUPS - SUPER ADMINISTRATOR (FIXED)
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

// Check role - only super_admin can access this page
if (SessionManager::get('role_level') !== 'super_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// ============================================================
// ENSURE BACKUPS TABLE EXISTS
// ============================================================
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS backups (
            id INT PRIMARY KEY AUTO_INCREMENT,
            tenant_id INT DEFAULT NULL,
            backup_type ENUM('full','database','files','tenant_data') NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL,
            file_sha256 VARCHAR(64) NOT NULL,
            status ENUM('pending','in_progress','completed','failed','restored') DEFAULT 'pending',
            started_at TIMESTAMP NULL,
            completed_at TIMESTAMP NULL,
            restored_at TIMESTAMP NULL,
            restored_by INT DEFAULT NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) {
    // Table exists
}

// ============================================================
// HANDLE BACKUP ACTIONS
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_backup':
                $backup_type = $_POST['backup_type'] ?? 'full';
                $tenant_id = !empty($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : null;
                
                // Create backup directory
                $backup_dir = '../../backups/';
                if (!file_exists($backup_dir)) {
                    if (!mkdir($backup_dir, 0777, true)) {
                        throw new Exception('Cannot create backup directory. Please check permissions.');
                    }
                }
                
                // Check if directory is writable
                if (!is_writable($backup_dir)) {
                    throw new Exception('Backup directory is not writable. Please check permissions.');
                }
                
                $filename = 'backup_' . date('Y-m-d_H-i-s') . '_' . $backup_type . '.sql';
                $filepath = $backup_dir . $filename;
                
                // Insert pending backup record
                $stmt = $db->prepare("
                    INSERT INTO backups (tenant_id, backup_type, file_path, file_size, file_sha256, status, started_at, created_by)
                    VALUES (?, ?, ?, 0, '', 'in_progress', NOW(), ?)
                ");
                $stmt->execute([$tenant_id, $backup_type, $filepath, SessionManager::get('user_id')]);
                $backup_id = $db->lastInsertId();
                
                // Try to create backup using different methods
                $backup_success = false;
                $error_message = '';
                
                // Method 1: Try using mysqldump
                $mysqldump_paths = [
                    'mysqldump',
                    'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                    'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
                    'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
                    '/usr/bin/mysqldump',
                    '/usr/local/bin/mysqldump'
                ];
                
                $mysqldump_found = false;
                foreach ($mysqldump_paths as $path) {
                    if (file_exists($path) || strpos($path, 'mysqldump') === 0) {
                        $mysqldump_found = true;
                        $command = $path . ' --user=' . DB_USER . ' --password=' . DB_PASS . ' --host=' . DB_HOST . ' ' . DB_NAME . ' > ' . $filepath . ' 2>&1';
                        exec($command, $output, $return_var);
                        
                        if ($return_var === 0 && file_exists($filepath) && filesize($filepath) > 0) {
                            $backup_success = true;
                            break;
                        }
                    }
                }
                
                // Method 2: If mysqldump fails, try using PHP's PDO to create backup
                if (!$backup_success) {
                    // Create backup using PHP
                    $backup_content = "-- Backup created on " . date('Y-m-d H:i:s') . "\n";
                    $backup_content .= "-- Database: " . DB_NAME . "\n\n";
                    
                    // Get all tables
                    $stmt = $db->query("SHOW TABLES");
                    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    foreach ($tables as $table) {
                        // Get create table statement
                        $stmt = $db->query("SHOW CREATE TABLE `$table`");
                        $create = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($create) {
                            $backup_content .= "DROP TABLE IF EXISTS `$table`;\n";
                            $backup_content .= $create['Create Table'] . ";\n\n";
                        }
                        
                        // Get table data
                        $stmt = $db->query("SELECT * FROM `$table`");
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (!empty($rows)) {
                            $columns = array_keys($rows[0]);
                            $columns_str = implode('`, `', $columns);
                            
                            foreach ($rows as $row) {
                                $values = array_map(function($value) use ($db) {
                                    if ($value === null) return 'NULL';
                                    return $db->quote($value);
                                }, $row);
                                
                                $backup_content .= "INSERT INTO `$table` (`$columns_str`) VALUES (" . implode(', ', $values) . ");\n";
                            }
                            $backup_content .= "\n";
                        }
                    }
                    
                    // Write backup file
                    if (file_put_contents($filepath, $backup_content) !== false) {
                        $backup_success = true;
                    } else {
                        $error_message = 'Failed to write backup file.';
                    }
                }
                
                if ($backup_success && file_exists($filepath)) {
                    $filesize = filesize($filepath);
                    $sha256 = hash_file('sha256', $filepath);
                    
                    // Update backup record
                    $stmt = $db->prepare("
                        UPDATE backups SET 
                            file_size = ?, 
                            file_sha256 = ?, 
                            status = 'completed',
                            completed_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$filesize, $sha256, $backup_id]);
                    
                    logActivity(SessionManager::get('user_id'), 'backup_created', "Created backup: $filename");
                    $action_result = [
                        'success' => true, 
                        'message' => "Backup created successfully: $filename (" . number_format($filesize / 1024, 2) . " KB)"
                    ];
                } else {
                    // Mark backup as failed
                    $stmt = $db->prepare("UPDATE backups SET status = 'failed' WHERE id = ?");
                    $stmt->execute([$backup_id]);
                    
                    throw new Exception('Failed to create backup. ' . $error_message);
                }
                break;
                
            case 'delete_backup':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception('Invalid backup ID.');
                }
                
                $stmt = $db->prepare("SELECT file_path FROM backups WHERE id = ?");
                $stmt->execute([$id]);
                $backup = $stmt->fetch();
                
                if ($backup && file_exists($backup['file_path'])) {
                    unlink($backup['file_path']);
                }
                
                $stmt = $db->prepare("DELETE FROM backups WHERE id = ?");
                $stmt->execute([$id]);
                
                $action_result = ['success' => true, 'message' => 'Backup deleted successfully.'];
                break;
                
            case 'restore_backup':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception('Invalid backup ID.');
                }
                
                $stmt = $db->prepare("SELECT file_path FROM backups WHERE id = ?");
                $stmt->execute([$id]);
                $backup = $stmt->fetch();
                
                if (!$backup || !file_exists($backup['file_path'])) {
                    throw new Exception('Backup file not found.');
                }
                
                // Read backup file and execute queries
                $backup_content = file_get_contents($backup['file_path']);
                if ($backup_content === false) {
                    throw new Exception('Failed to read backup file.');
                }
                
                // Split queries
                $queries = explode(";\n", $backup_content);
                $restored = 0;
                
                $db->beginTransaction();
                try {
                    foreach ($queries as $query) {
                        $query = trim($query);
                        if (!empty($query)) {
                            $db->exec($query);
                            $restored++;
                        }
                    }
                    $db->commit();
                    
                    $stmt = $db->prepare("
                        UPDATE backups SET 
                            status = 'restored', 
                            restored_at = NOW(), 
                            restored_by = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([SessionManager::get('user_id'), $id]);
                    
                    logActivity(SessionManager::get('user_id'), 'backup_restored', "Restored backup ID: $id");
                    $action_result = ['success' => true, 'message' => "Backup restored successfully! ($restored queries executed)"];
                } catch (Exception $e) {
                    $db->rollBack();
                    throw new Exception('Failed to restore backup: ' . $e->getMessage());
                }
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ============================================================
// FETCH BACKUPS
// ============================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(backup_type LIKE ? OR file_path LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

if (!empty($type_filter)) {
    $where_conditions[] = "backup_type = ?";
    $params[] = $type_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total
$count_sql = "SELECT COUNT(*) as total FROM backups $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_backups = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_backups / $limit);

// Fetch backups
$sql = "
    SELECT 
        b.*,
        u.full_name as created_by_name,
        u.email as created_by_email,
        r.full_name as restored_by_name
    FROM backups b
    LEFT JOIN users u ON b.created_by = u.id
    LEFT JOIN users r ON b.restored_by = r.id
    $where_clause
    ORDER BY b.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$backups = $stmt->fetchAll();

// ============================================================
// FETCH TENANTS FOR FILTER
// ============================================================
$tenants = [];
try {
    $stmt = $db->query("SELECT id, name FROM tenants WHERE deleted_at IS NULL ORDER BY name");
    $tenants = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH STATISTICS
// ============================================================
$stats = [
    'total' => 0,
    'completed' => 0,
    'pending' => 0,
    'failed' => 0,
    'restored' => 0,
    'total_size' => 0
];

try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM backups");
    $stats['total'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM backups WHERE status = 'completed'");
    $stats['completed'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM backups WHERE status = 'pending' OR status = 'in_progress'");
    $stats['pending'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM backups WHERE status = 'failed'");
    $stats['failed'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM backups WHERE status = 'restored'");
    $stats['restored'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT SUM(file_size) as total FROM backups");
    $stats['total_size'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    // Continue
}

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<!-- The rest of the HTML remains the same as the previous version -->
<style>
    /* ============================================================
       BACKUPS - PRO STYLES
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
        padding: 8px 18px;
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
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25);
    }
    .btn-outline {
        padding: 8px 16px;
        background: transparent;
        color: var(--gray-600);
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.82rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-outline:hover {
        background: var(--gray-50);
        border-color: var(--gray-300);
    }
    .btn-sm {
        padding: 4px 10px;
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
    .btn-sm.success { background: #ECFDF5; color: #065F46; }
    .btn-sm.success:hover { background: #D1FAE5; }
    .btn-sm.danger { background: #FEF2F2; color: #991B1B; }
    .btn-sm.danger:hover { background: #FEE2E2; }
    .btn-sm.warning { background: #FFFBEB; color: #92400E; }
    .btn-sm.warning:hover { background: #FEF3C7; }
    .btn-sm.info { background: #EFF6FF; color: #1E40AF; }
    .btn-sm.info:hover { background: #DBEAFE; }
    .btn-sm.purple { background: #F5F3FF; color: #5B21B6; }
    .btn-sm.purple:hover { background: #EDE9FE; }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    .stat-item {
        background: white;
        border-radius: 12px;
        padding: 14px 18px;
        border: 1px solid var(--gray-200);
        text-align: center;
        transition: var(--transition);
    }
    .stat-item:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-2px);
    }
    .stat-item .number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
    }
    .stat-item .number.green { color: var(--secondary); }
    .stat-item .number.red { color: var(--danger); }
    .stat-item .number.yellow { color: var(--warning); }
    .stat-item .number.purple { color: #8B5CF6; }
    .stat-item .label {
        font-size: 0.7rem;
        color: var(--gray-500);
        margin-top: 2px;
    }

    .filter-bar-pro {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 14px 20px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        box-shadow: var(--shadow);
    }
    .filter-bar-pro .search-wrap {
        flex: 1;
        min-width: 200px;
        display: flex;
        align-items: center;
        background: var(--gray-50);
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        padding: 6px 14px;
        transition: var(--transition);
    }
    .filter-bar-pro .search-wrap:focus-within {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
    }
    .filter-bar-pro .search-wrap i {
        color: var(--gray-400);
        font-size: 0.85rem;
    }
    .filter-bar-pro .search-wrap input {
        border: none;
        outline: none;
        background: transparent;
        padding: 6px 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        width: 100%;
        color: var(--gray-700);
    }
    .filter-bar-pro select {
        padding: 8px 14px;
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.82rem;
        background: var(--gray-50);
        color: var(--gray-700);
        cursor: pointer;
        transition: var(--transition);
        min-width: 120px;
    }
    .filter-bar-pro select:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
    }
    .filter-bar-pro .btn-filter {
        padding: 8px 18px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .filter-bar-pro .btn-filter:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
    }
    .filter-bar-pro .btn-clear {
        padding: 8px 14px;
        background: transparent;
        color: var(--gray-500);
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.8rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .filter-bar-pro .btn-clear:hover {
        background: var(--gray-50);
        border-color: var(--gray-300);
    }

    .table-container {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        overflow: hidden;
        box-shadow: var(--shadow);
    }
    .table-container .table-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        background: var(--gray-50);
    }
    .table-container .table-header .table-title {
        font-weight: 600;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .table-container .table-header .table-title .count {
        background: var(--primary);
        color: white;
        padding: 0 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
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
        padding: 10px 14px;
        text-align: left;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--gray-500);
        border-bottom: 1px solid var(--gray-200);
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 2;
        background: var(--gray-50);
    }
    .data-table tbody td {
        padding: 8px 14px;
        border-bottom: 1px solid var(--gray-100);
        vertical-align: middle;
    }
    .data-table tbody tr:last-child td {
        border-bottom: none;
    }
    .data-table tbody tr:hover {
        background: var(--gray-50);
    }

    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    .badge-status .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .badge-status.completed { background: #ECFDF5; color: #065F46; }
    .badge-status.completed .dot { background: #10B981; }
    .badge-status.pending { background: #FFFBEB; color: #92400E; }
    .badge-status.pending .dot { background: #F59E0B; }
    .badge-status.failed { background: #FEF2F2; color: #991B1B; }
    .badge-status.failed .dot { background: #EF4444; }
    .badge-status.restored { background: #EFF6FF; color: #1E40AF; }
    .badge-status.restored .dot { background: #3B82F6; }
    .badge-status.in_progress { background: #F5F3FF; color: #5B21B6; }
    .badge-status.in_progress .dot { background: #8B5CF6; }

    .badge-type {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 500;
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .badge-type.full { background: #F5F3FF; color: #5B21B6; }
    .badge-type.database { background: #EFF6FF; color: #1E40AF; }
    .badge-type.files { background: #ECFDF5; color: #065F46; }
    .badge-type.tenant_data { background: #FFFBEB; color: #92400E; }

    .pagination-pro {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding: 14px 20px;
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        margin-top: 16px;
        box-shadow: var(--shadow);
    }
    .pagination-pro .info {
        font-size: 0.82rem;
        color: var(--gray-500);
    }
    .pagination-pro .pages {
        display: flex;
        gap: 4px;
        align-items: center;
    }
    .pagination-pro .pages a,
    .pagination-pro .pages span {
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 0.82rem;
        text-decoration: none;
        color: var(--gray-600);
        transition: var(--transition);
        min-width: 36px;
        text-align: center;
        border: 1px solid transparent;
    }
    .pagination-pro .pages a:hover {
        background: var(--gray-100);
        border-color: var(--gray-200);
    }
    .pagination-pro .pages .active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    .pagination-pro .pages .disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    .empty-state-pro {
        text-align: center;
        padding: 48px 20px;
        color: var(--gray-500);
    }
    .empty-state-pro i {
        font-size: 3rem;
        color: var(--gray-300);
        display: block;
        margin-bottom: 12px;
    }
    .empty-state-pro h4 {
        color: var(--gray-700);
        margin-bottom: 4px;
        font-size: 1rem;
    }
    .empty-state-pro p {
        font-size: 0.85rem;
        color: var(--gray-400);
    }

    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.4);
        z-index: 300;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .modal-overlay.active {
        display: flex;
    }
    .modal {
        background: white;
        border-radius: var(--radius);
        max-width: 480px;
        width: 100%;
        padding: 28px 32px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        animation: modalIn 0.25s ease;
    }
    @keyframes modalIn {
        from { transform: scale(0.95) translateY(10px); opacity: 0; }
        to { transform: scale(1) translateY(0); opacity: 1; }
    }
    .modal .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }
    .modal .modal-header h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--gray-800);
    }
    .modal .modal-header .close-btn {
        background: none;
        border: none;
        font-size: 1.4rem;
        color: var(--gray-400);
        cursor: pointer;
        transition: var(--transition);
        padding: 0 4px;
    }
    .modal .modal-header .close-btn:hover {
        color: var(--gray-600);
    }
    .modal .form-group {
        margin-bottom: 14px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .modal .form-group label {
        font-weight: 600;
        font-size: 0.82rem;
        color: var(--gray-700);
    }
    .modal .form-group label .required {
        color: var(--danger);
        margin-left: 2px;
    }
    .modal .form-group select {
        padding: 10px 14px;
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        transition: var(--transition);
        background: var(--gray-50);
        color: var(--gray-700);
        width: 100%;
    }
    .modal .form-group select:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
    }
    .modal .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 1px solid var(--gray-200);
    }
    .modal .form-actions .btn {
        padding: 8px 20px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .modal .form-actions .btn-primary {
        background: var(--primary);
        color: white;
    }
    .modal .form-actions .btn-primary:hover {
        background: var(--primary-dark);
    }
    .modal .form-actions .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .modal .form-actions .btn-secondary:hover {
        background: var(--gray-200);
    }

    .toast-container {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 999;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .toast {
        padding: 14px 20px;
        border-radius: 10px;
        color: white;
        font-size: 0.85rem;
        font-weight: 500;
        box-shadow: var(--shadow-hover);
        animation: slideIn 0.3s ease;
        min-width: 280px;
        max-width: 400px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .toast.success { background: var(--secondary); }
    .toast.error { background: var(--danger); }
    @keyframes slideIn {
        from { opacity: 0; transform: translateX(100px); }
        to { opacity: 1; transform: translateX(0); }
    }

    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .filter-bar-pro { flex-direction: column; align-items: stretch; }
        .filter-bar-pro .search-wrap { min-width: auto; }
        .filter-bar-pro select { width: 100%; }
        .table-container { overflow-x: auto; }
        .data-table { font-size: 0.78rem; }
        .data-table th, .data-table td { padding: 6px 10px; }
        .pagination-pro { flex-direction: column; align-items: center; }
        .page-header { flex-direction: column; align-items: flex-start; }
        .modal { padding: 20px; margin: 10px; }
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
        .stat-item { padding: 10px 12px; }
        .stat-item .number { font-size: 1.2rem; }
        .data-table th, .data-table td { padding: 4px 8px; font-size: 0.7rem; }
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

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-archive" style="color:var(--primary);margin-right:8px;"></i> Backups
                    <small>Manage system backups and restores</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button onclick="openModal('createBackupModal')" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Create Backup
                </button>
                <button onclick="location.reload()" class="btn-outline">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item"><div class="number"><?php echo number_format($stats['total']); ?></div><div class="label">Total Backups</div></div>
            <div class="stat-item"><div class="number green"><?php echo number_format($stats['completed']); ?></div><div class="label">Completed</div></div>
            <div class="stat-item"><div class="number yellow"><?php echo number_format($stats['pending']); ?></div><div class="label">Pending</div></div>
            <div class="stat-item"><div class="number red"><?php echo number_format($stats['failed']); ?></div><div class="label">Failed</div></div>
            <div class="stat-item"><div class="number purple"><?php echo number_format($stats['restored']); ?></div><div class="label">Restored</div></div>
            <div class="stat-item"><div class="number"><?php echo number_format($stats['total_size'] / 1024 / 1024, 2); ?> MB</div><div class="label">Total Size</div></div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar-pro">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                <div class="search-wrap" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search backups..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="type">
                    <option value="">All Types</option>
                    <option value="full" <?php echo $type_filter === 'full' ? 'selected' : ''; ?>>Full</option>
                    <option value="database" <?php echo $type_filter === 'database' ? 'selected' : ''; ?>>Database</option>
                    <option value="files" <?php echo $type_filter === 'files' ? 'selected' : ''; ?>>Files</option>
                    <option value="tenant_data" <?php echo $type_filter === 'tenant_data' ? 'selected' : ''; ?>>Tenant Data</option>
                </select>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    <option value="restored" <?php echo $status_filter === 'restored' ? 'selected' : ''; ?>>Restored</option>
                    <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if (!empty($search) || !empty($type_filter) || !empty($status_filter)): ?>
                    <a href="backups.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Backups Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list" style="color:var(--primary);"></i> All Backups
                    <span class="count"><?php echo number_format($total_backups); ?></span>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Type</th>
                        <th>File</th>
                        <th>Size</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Created</th>
                        <th style="width:100px;text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($backups) > 0): ?>
                        <?php foreach ($backups as $index => $backup): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <span class="badge-type <?php echo $backup['backup_type']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $backup['backup_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size:0.82rem;max-width:200px;word-wrap:break-word;">
                                        <?php echo htmlspecialchars(basename($backup['file_path'])); ?>
                                    </div>
                                </td>
                                <td style="font-size:0.8rem;">
                                    <?php echo number_format($backup['file_size'] / 1024, 2); ?> KB
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $backup['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst(str_replace('_', ' ', $backup['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size:0.8rem;"><?php echo htmlspecialchars($backup['created_by_name'] ?? 'System'); ?></div>
                                </td>
                                <td style="font-size:0.75rem;color:var(--gray-500);">
                                    <?php echo date('M j, Y g:i A', strtotime($backup['created_at'])); ?>
                                </td>
                                <td>
                                    <div style="display:flex;gap:4px;flex-wrap:wrap;justify-content:center;">
                                        <?php if ($backup['status'] === 'completed'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="restore_backup">
                                                <input type="hidden" name="id" value="<?php echo $backup['id']; ?>">
                                                <button type="submit" class="btn-sm success" onclick="return confirm('Restore this backup? This will overwrite current data.')" title="Restore">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            </form>
                                            <a href="<?php echo htmlspecialchars($backup['file_path']); ?>" download class="btn-sm info" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        <?php endif; ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_backup">
                                            <input type="hidden" name="id" value="<?php echo $backup['id']; ?>">
                                            <button type="submit" class="btn-sm danger" onclick="return confirm('Delete this backup?')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state-pro">
                                    <i class="fas fa-archive"></i>
                                    <h4>No backups found</h4>
                                    <p>Create your first backup to protect your data.</p>
                                    <button onclick="openModal('createBackupModal')" class="btn-primary" style="margin-top:12px;">
                                        <i class="fas fa-plus-circle"></i> Create Backup
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-pro">
            <div class="info">
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_backups); ?></strong> of <strong><?php echo number_format($total_backups); ?></strong> backups
            </div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) {
                    echo '<a href="?page=1&search=' . urlencode($search) . '&type=' . urlencode($type_filter) . '&status=' . urlencode($status_filter) . '">1</a>';
                    if ($start_page > 2) echo '<span>…</span>';
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>…</span>';
                    echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&type=' . urlencode($type_filter) . '&status=' . urlencode($status_filter) . '">' . $total_pages . '</a>';
                }
                ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Create Backup Modal -->
<div class="modal-overlay" id="createBackupModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color:var(--primary);"></i> Create Backup</h3>
            <button class="close-btn" onclick="closeModal('createBackupModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create_backup">
            <div class="form-group">
                <label>Backup Type <span class="required">*</span></label>
                <select name="backup_type" required>
                    <option value="full">Full Backup</option>
                    <option value="database">Database Only</option>
                    <option value="files">Files Only</option>
                    <option value="tenant_data">Tenant Data</option>
                </select>
                <div class="help-text">Full backup includes database and files. Database only includes SQL data.</div>
            </div>
            <div class="form-group">
                <label>Tenant (Optional)</label>
                <select name="tenant_id">
                    <option value="">All Tenants</option>
                    <?php foreach ($tenants as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="help-text">Leave empty to backup all tenants.</div>
            </div>
            <div style="background:#F5F3FF;padding:12px 16px;border-radius:8px;color:#5B21B6;font-size:0.85rem;border:1px solid #EDE9FE;margin-bottom:16px;">
                <i class="fas fa-info-circle"></i>
                <strong>Note:</strong> Creating a backup may take a few moments depending on your data size. The backup will be stored in the <code>backups/</code> directory.
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createBackupModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Backup</button>
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
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// ============================================================
// SEARCH
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
</script>
</body>
</html>