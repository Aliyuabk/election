<?php
$page_title = "Backup & Restore";
require_once 'includes/db.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// ============================================================
// GET VALID USER ID
// ============================================================
$logUserId = getValidUserId();

// ============================================================
// HANDLE ACTIONS
// ============================================================
$message = '';
$error = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_backup':
                $backup_type = $_POST['backup_type'] ?? 'full';
                $result = createBackup($backup_type);
                $message = "Backup created successfully. " . $result['message'];
                $message_type = 'success';
                break;
                
            case 'restore_backup':
                $backup_id = (int)($_POST['backup_id'] ?? 0);
                $result = restoreBackup($backup_id);
                $message = "Backup restored successfully. " . $result['message'];
                $message_type = 'success';
                break;
                
            case 'delete_backup':
                $backup_id = (int)($_POST['backup_id'] ?? 0);
                $result = deleteBackup($backup_id);
                $message = "Backup deleted successfully.";
                $message_type = 'success';
                break;
                
            case 'download_backup':
                $backup_id = (int)($_POST['backup_id'] ?? 0);
                downloadBackup($backup_id);
                exit;
                break;
                
            case 'schedule_backup':
                $backup_type = $_POST['backup_type'] ?? 'full';
                $frequency = $_POST['frequency'] ?? 'daily';
                $result = scheduleBackup($backup_type, $frequency);
                $message = "Backup scheduled successfully.";
                $message_type = 'success';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        $message_type = 'error';
    }
}

// ============================================================
// GET VALID USER ID WITH FALLBACK
// ============================================================
function getValidBackupUserId() {
    // First try to get from session
    if (isset($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$userId]);
            if ($stmt->fetch()) {
                return $userId;
            }
        } catch (Exception $e) {
            // Continue to fallback
        }
    }
    
    // Try to find any admin user
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT id FROM users WHERE role_id = 1 OR email LIKE '%admin%' LIMIT 1");
        $stmt->execute();
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            return (int)$user['id'];
        }
    } catch (Exception $e) {
        // Continue to fallback
    }
    
    // Try to find any user at all
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT id FROM users LIMIT 1");
        $stmt->execute();
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            return (int)$user['id'];
        }
    } catch (Exception $e) {
        // Continue to fallback
    }
    
    // If no user exists, we need to create a system user or return 0
    // For now, return 0 (will be handled in insert)
    return 0;
}

// ============================================================
// BACKUP FUNCTIONS
// ============================================================
function createBackup($type) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $logUserId = getValidBackupUserId();
    
    // Create backup directory
    $backup_dir = __DIR__ . '/../backups/';
    if (!is_dir($backup_dir)) {
        if (!mkdir($backup_dir, 0777, true)) {
            throw new Exception("Failed to create backup directory.");
        }
    }
    
    // Check if directory is writable
    if (!is_writable($backup_dir)) {
        throw new Exception("Backup directory is not writable.");
    }
    
    $filename = 'backup_' . date('Y-m-d_His') . '_' . $type . '.sql';
    $filepath = $backup_dir . $filename;
    
    // Get database credentials from config
    require_once __DIR__ . '/../../config/config.php';
    
    // Method 1: Try using PHP's exec with mysqldump
    $mysqldump_found = false;
    $output = [];
    $return_var = 0;
    
    // Try different mysqldump paths
    $mysqldump_paths = [
        'mysqldump',
        '"C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe"',
        '"C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe"',
        '"C:\\Program Files (x86)\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe"',
        '/usr/bin/mysqldump',
        '/usr/local/bin/mysqldump'
    ];
    
    foreach ($mysqldump_paths as $path) {
        $command = sprintf(
            '%s --host=%s --user=%s --password=%s --no-tablespaces --skip-extended-insert --complete-insert %s > %s 2>&1',
            $path,
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME,
            $filepath
        );
        
        exec($command, $output, $return_var);
        
        if ($return_var === 0 && file_exists($filepath) && filesize($filepath) > 0) {
            $mysqldump_found = true;
            break;
        }
    }
    
    // Method 2: If mysqldump failed, try using PDO to create backup
    if (!$mysqldump_found) {
        $backup_data = createBackupWithPDO();
        if (file_put_contents($filepath, $backup_data) === false) {
            throw new Exception("Failed to write backup file.");
        }
    }
    
    // Verify backup file
    if (!file_exists($filepath) || filesize($filepath) === 0) {
        throw new Exception("Backup file is empty or was not created.");
    }
    
    $file_size = filesize($filepath);
    $file_sha256 = hash_file('sha256', $filepath);
    
    // Insert backup record - ALWAYS provide created_by
    // If no valid user ID, use 1 (assuming admin user exists) or try to create one
    if ($logUserId > 0) {
        $stmt = $conn->prepare("
            INSERT INTO backups (backup_type, file_path, file_size, file_sha256, status, created_by, created_at) 
            VALUES (?, ?, ?, ?, 'completed', ?, NOW())
        ");
        $stmt->execute([$type, $filepath, $file_size, $file_sha256, $logUserId]);
    } else {
        // Try to get any user ID from the database
        $stmt = $conn->prepare("SELECT id FROM users LIMIT 1");
        $stmt->execute();
        $user = $stmt->fetch();
        if ($user) {
            $userId = $user['id'];
        } else {
            // If absolutely no user exists, create a system user or use 1
            // Since we can't create a user here, we'll use 1 and hope it exists
            // Or we could alter the table to allow NULL
            $userId = 1;
        }
        
        $stmt = $conn->prepare("
            INSERT INTO backups (backup_type, file_path, file_size, file_sha256, status, created_by, created_at) 
            VALUES (?, ?, ?, ?, 'completed', ?, NOW())
        ");
        $stmt->execute([$type, $filepath, $file_size, $file_sha256, $userId]);
    }
    
    // Log activity
    logActivity($logUserId, null, 'backup_created', "Created backup: " . $filename);
    
    return [
        'message' => "Backup file: " . $filename . " (" . number_format($file_size / 1024 / 1024, 2) . " MB)",
        'file' => $filename,
        'path' => $filepath
    ];
}

function createBackupWithPDO() {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get all table names
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $backup = "-- ============================================================\n";
    $backup .= "-- DATABASE BACKUP\n";
    $backup .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $backup .= "-- ============================================================\n\n";
    $backup .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    
    foreach ($tables as $table) {
        // Get table structure
        $stmt = $conn->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $create_table = $row['Create Table'] ?? '';
        
        $backup .= "-- ------------------------------------------------------------\n";
        $backup .= "-- Table: `$table`\n";
        $backup .= "-- ------------------------------------------------------------\n\n";
        $backup .= "DROP TABLE IF EXISTS `$table`;\n";
        $backup .= $create_table . ";\n\n";
        
        // Get table data
        $stmt = $conn->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
            $values = [];
            
            foreach ($rows as $row) {
                $escaped = array_map(function($value) use ($conn) {
                    if ($value === null) return 'NULL';
                    return $conn->quote($value);
                }, array_values($row));
                
                $values[] = "(" . implode(', ', $escaped) . ")";
            }
            
            $backup .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES \n";
            $backup .= implode(",\n", $values) . ";\n\n";
        } else {
            $backup .= "-- Table `$table` is empty\n\n";
        }
    }
    
    $backup .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    
    return $backup;
}

function restoreBackup($backup_id) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $logUserId = getValidBackupUserId();
    
    // Get backup info
    $stmt = $conn->prepare("SELECT * FROM backups WHERE id = ? AND status = 'completed'");
    $stmt->execute([$backup_id]);
    $backup = $stmt->fetch();
    
    if (!$backup) {
        throw new Exception("Backup not found.");
    }
    
    if (!file_exists($backup['file_path'])) {
        throw new Exception("Backup file not found.");
    }
    
    // Read SQL file
    $sql = file_get_contents($backup['file_path']);
    if ($sql === false) {
        throw new Exception("Failed to read backup file.");
    }
    
    // Split SQL into individual statements
    $statements = explode(";\n", $sql);
    
    // Start transaction
    $conn->beginTransaction();
    
    try {
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $conn->exec($statement);
            }
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        throw new Exception("Failed to restore backup: " . $e->getMessage());
    }
    
    // Update backup status
    if ($logUserId > 0) {
        $stmt = $conn->prepare("UPDATE backups SET status = 'restored', restored_at = NOW(), restored_by = ? WHERE id = ?");
        $stmt->execute([$logUserId, $backup_id]);
    } else {
        $stmt = $conn->prepare("UPDATE backups SET status = 'restored', restored_at = NOW() WHERE id = ?");
        $stmt->execute([$backup_id]);
    }
    
    logActivity($logUserId, null, 'backup_restored', "Restored backup: " . basename($backup['file_path']));
    
    return ['message' => "Backup restored successfully."];
}

function deleteBackup($backup_id) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $logUserId = getValidBackupUserId();
    
    $stmt = $conn->prepare("SELECT file_path FROM backups WHERE id = ?");
    $stmt->execute([$backup_id]);
    $backup = $stmt->fetch();
    
    if ($backup && file_exists($backup['file_path'])) {
        unlink($backup['file_path']);
    }
    
    $stmt = $conn->prepare("DELETE FROM backups WHERE id = ?");
    $stmt->execute([$backup_id]);
    
    logActivity($logUserId, null, 'backup_deleted', "Deleted backup ID: " . $backup_id);
    
    return true;
}

function downloadBackup($backup_id) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM backups WHERE id = ? AND status = 'completed'");
    $stmt->execute([$backup_id]);
    $backup = $stmt->fetch();
    
    if (!$backup || !file_exists($backup['file_path'])) {
        die("Backup file not found.");
    }
    
    // Log download
    logActivity(getValidBackupUserId(), null, 'backup_downloaded', "Downloaded backup: " . basename($backup['file_path']));
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($backup['file_path']) . '"');
    header('Content-Length: ' . filesize($backup['file_path']));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Clear output buffer
    ob_clean();
    flush();
    
    readfile($backup['file_path']);
    exit;
}

function scheduleBackup($type, $frequency) {
    $logUserId = getValidBackupUserId();
    
    // Save schedule settings
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Update or insert backup schedule settings
    $stmt = $conn->prepare("
        INSERT INTO system_settings (`key`, `value`, type, updated_at) 
        VALUES ('backup_schedule_type', ?, 'string', NOW())
        ON DUPLICATE KEY UPDATE value = ?, updated_at = NOW()
    ");
    $stmt->execute([$type, $type]);
    
    $stmt = $conn->prepare("
        INSERT INTO system_settings (`key`, `value`, type, updated_at) 
        VALUES ('backup_schedule_frequency', ?, 'string', NOW())
        ON DUPLICATE KEY UPDATE value = ?, updated_at = NOW()
    ");
    $stmt->execute([$frequency, $frequency]);
    
    logActivity($logUserId, null, 'backup_scheduled', "Scheduled $frequency $type backup");
    return true;
}

// ============================================================
// GET BACKUPS
// ============================================================
$backups = $conn->query("
    SELECT b.*, u.full_name as creator_name 
    FROM backups b
    LEFT JOIN users u ON b.created_by = u.id
    ORDER BY b.created_at DESC
")->fetchAll();

// Get backup stats
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'restored' THEN 1 ELSE 0 END) as restored,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        COALESCE(SUM(file_size), 0) as total_size,
        COALESCE(MAX(file_size), 0) as largest_size,
        COALESCE(MIN(file_size), 0) as smallest_size
    FROM backups
")->fetch();

// Get latest backup time
$latestBackup = $conn->query("
    SELECT created_at FROM backups 
    WHERE status = 'completed' 
    ORDER BY created_at DESC 
    LIMIT 1
")->fetch();

include 'includes/base.php';
?>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/header.php'; ?>

<style>
/* ============================================================
   BACKUP STYLES
   ============================================================ */

/* Stats Grid */
.backup-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.backup-stats .stat-card {
    background: white;
    border-radius: 14px;
    padding: 16px 20px;
    text-align: center;
    border: 1px solid #eef3f8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    transition: all 0.2s ease;
}

.backup-stats .stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
}

.backup-stats .stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: #0b1a33;
    line-height: 1.2;
}

.backup-stats .stat-label {
    font-size: 0.7rem;
    color: #6d83a5;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-top: 4px;
}

.backup-stats .stat-icon {
    font-size: 1.5rem;
    display: block;
    margin-bottom: 8px;
}

.stat-icon.total { color: #4f9cf7; }
.stat-icon.completed { color: #10b981; }
.stat-icon.restored { color: #8b5cf6; }
.stat-icon.size { color: #f59e0b; }

/* Backup Actions */
.backup-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 24px;
}

.backup-actions .action-card {
    background: white;
    border-radius: 14px;
    padding: 20px 24px;
    border: 1px solid #eef3f8;
    transition: all 0.2s ease;
}

.backup-actions .action-card:hover {
    border-color: #4f9cf7;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
}

.backup-actions .action-card .action-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.backup-actions .action-card .action-header i {
    font-size: 1.5rem;
    color: #4f9cf7;
}

.backup-actions .action-card .action-title {
    font-weight: 600;
    color: #0b1a33;
    font-size: 1rem;
}

.backup-actions .action-card .action-desc {
    font-size: 0.8rem;
    color: #6d83a5;
    margin-bottom: 12px;
}

.backup-actions .action-card .action-form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.backup-actions .action-card .action-form select {
    padding: 8px 14px;
    border: 1px solid #dce6f0;
    border-radius: 8px;
    font-size: 0.85rem;
    background: white;
    color: #1f3149;
    flex: 1;
    min-width: 120px;
}

.backup-actions .action-card .action-form select:focus {
    outline: none;
    border-color: #4f9cf7;
    box-shadow: 0 0 0 3px rgba(79, 156, 247, 0.1);
}

.backup-actions .action-card .action-form .btn-primary {
    padding: 8px 20px;
    font-size: 0.85rem;
    white-space: nowrap;
}

/* Backup List */
.backup-list {
    background: white;
    border-radius: 14px;
    border: 1px solid #eef3f8;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}

.backup-list .list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #f0f4fa;
    background: #f8faff;
}

.backup-list .list-header h3 {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1f3149;
    display: flex;
    align-items: center;
    gap: 8px;
}

.backup-list .list-header h3 i {
    color: #4f9cf7;
}

.backup-list .list-header .latest-info {
    font-size: 0.8rem;
    color: #6d83a5;
}

.backup-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 14px 20px;
    border-bottom: 1px solid #f5f8fc;
    transition: background 0.15s;
}

.backup-item:hover {
    background: #f8faff;
}

.backup-item:last-child {
    border-bottom: none;
}

.backup-item .backup-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.2rem;
}

.backup-item .backup-icon.full { background: #dbeafe; color: #4f9cf7; }
.backup-item .backup-icon.database { background: #d1fae5; color: #10b981; }
.backup-item .backup-icon.files { background: #fef3c7; color: #f59e0b; }

.backup-item .backup-info {
    flex: 1;
    min-width: 0;
}

.backup-item .backup-name {
    font-weight: 500;
    color: #0b1a33;
    font-size: 0.9rem;
    word-break: break-all;
}

.backup-item .backup-meta {
    font-size: 0.75rem;
    color: #6d83a5;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.backup-item .backup-meta .separator {
    color: #dce6f0;
}

.backup-item .backup-status {
    font-size: 0.65rem;
    padding: 2px 14px;
    border-radius: 30px;
    font-weight: 500;
    white-space: nowrap;
}

.backup-item .backup-status.completed { background: #d1fae5; color: #065f46; }
.backup-item .backup-status.restored { background: #dbeafe; color: #1e40af; }
.backup-item .backup-status.failed { background: #fee2e2; color: #991b1b; }
.backup-item .backup-status.pending { background: #fef3c7; color: #92400e; }

.backup-item .backup-size {
    font-size: 0.8rem;
    color: #6d83a5;
    white-space: nowrap;
    font-weight: 500;
}

.backup-item .backup-actions {
    display: flex;
    gap: 2px;
    margin: 0;
}

.backup-item .backup-actions .btn-icon {
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #6d83a5;
    cursor: pointer;
    transition: all 0.15s;
    font-size: 0.85rem;
    position: relative;
}

.backup-item .backup-actions .btn-icon:hover {
    background: #f0f5fe;
    color: #1f3d6b;
    transform: translateY(-1px);
}

.backup-item .backup-actions .btn-icon .tooltip {
    display: none;
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%);
    background: #0b1a33;
    color: white;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.6rem;
    white-space: nowrap;
    z-index: 10;
}

.backup-item .backup-actions .btn-icon:hover .tooltip {
    display: block;
}

.backup-item .backup-actions .btn-icon .tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #0b1a33;
}

.backup-item .backup-actions .btn-icon.download { color: #4f9cf7; }
.backup-item .backup-actions .btn-icon.download:hover { background: #e8f0fe; }
.backup-item .backup-actions .btn-icon.restore { color: #f59e0b; }
.backup-item .backup-actions .btn-icon.restore:hover { background: #fef3c7; }
.backup-item .backup-actions .btn-icon.delete { color: #ef4444; }
.backup-item .backup-actions .btn-icon.delete:hover { background: #fee2e2; }

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #8b9bb5;
}

.empty-state i {
    font-size: 3rem;
    color: #dce6f0;
    display: block;
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 1.1rem;
    color: #1f3149;
    margin-bottom: 8px;
}

.empty-state p {
    font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 768px) {
    .backup-actions {
        grid-template-columns: 1fr;
    }
    
    .backup-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .backup-item {
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .backup-item .backup-info {
        width: 100%;
        order: 2;
    }
    
    .backup-item .backup-icon {
        order: 1;
    }
    
    .backup-item .backup-status {
        order: 3;
    }
    
    .backup-item .backup-size {
        order: 4;
    }
    
    .backup-item .backup-actions {
        order: 5;
        width: 100%;
        justify-content: flex-start;
    }
}

@media (max-width: 480px) {
    .backup-stats {
        grid-template-columns: 1fr 1fr;
    }
    
    .backup-actions .action-card .action-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .backup-actions .action-card .action-form select {
        width: 100%;
    }
    
    .backup-item .backup-meta {
        flex-direction: column;
        gap: 2px;
    }
    
    .backup-item .backup-actions .btn-icon {
        width: 28px;
        height: 28px;
        font-size: 0.75rem;
    }
}
</style>

<main class="main-content">
    <!-- ============================================================
    PAGE HEADER
    ============================================================ -->
    <div class="page-header">
        <div class="header-left">
            <h1>
                <i class="fas fa-database" style="color:#4f9cf7;"></i>
                Backup & Restore
                <span class="page-badge"><?php echo number_format($stats['total'] ?? 0); ?></span>
            </h1>
            <p class="subtitle">Manage database backups and restores</p>
        </div>
    </div>

    <!-- ============================================================
    ALERTS
    ============================================================ -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type ?: 'success'; ?>">
        <i class="fas fa-<?php echo $message_type === 'error' ? 'exclamation-circle' : 'check-circle'; ?>"></i>
        <?php echo $message; ?>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>

    <!-- ============================================================
    STATISTICS
    ============================================================ -->
    <div class="backup-stats">
        <div class="stat-card">
            <span class="stat-icon total"><i class="fas fa-database"></i></span>
            <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
            <div class="stat-label">Total Backups</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon completed"><i class="fas fa-check-circle"></i></span>
            <div class="stat-number"><?php echo number_format($stats['completed'] ?? 0); ?></div>
            <div class="stat-label">Completed</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon restored"><i class="fas fa-undo"></i></span>
            <div class="stat-number"><?php echo number_format($stats['restored'] ?? 0); ?></div>
            <div class="stat-label">Restored</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon size"><i class="fas fa-weight-hanging"></i></span>
            <div class="stat-number"><?php echo number_format(($stats['total_size'] ?? 0) / 1024 / 1024, 1); ?> MB</div>
            <div class="stat-label">Total Size</div>
        </div>
    </div>

    <!-- ============================================================
    BACKUP ACTIONS
    ============================================================ -->
    <div class="backup-actions">
        <!-- Manual Backup -->
        <div class="action-card">
            <div class="action-header">
                <i class="fas fa-plus-circle"></i>
                <div class="action-title">Create Backup</div>
            </div>
            <div class="action-desc">Create a manual backup of your database</div>
            <form method="POST" class="action-form">
                <input type="hidden" name="action" value="create_backup">
                <select name="backup_type" required>
                    <option value="full">Full Backup</option>
                    <option value="database">Database Only</option>
                    <option value="files">Files Only</option>
                </select>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-play"></i> Create
                </button>
            </form>
        </div>

        <!-- Schedule Backup -->
        <div class="action-card">
            <div class="action-header">
                <i class="fas fa-clock"></i>
                <div class="action-title">Schedule Backup</div>
            </div>
            <div class="action-desc">Automate backups on a schedule</div>
            <form method="POST" class="action-form">
                <input type="hidden" name="action" value="schedule_backup">
                <select name="backup_type" required>
                    <option value="full">Full Backup</option>
                    <option value="database">Database Only</option>
                </select>
                <select name="frequency" required>
                    <option value="hourly">Hourly</option>
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-calendar-plus"></i> Schedule
                </button>
            </form>
        </div>
    </div>

    <!-- ============================================================
    BACKUP HISTORY
    ============================================================ -->
    <div class="backup-list">
        <div class="list-header">
            <h3><i class="fas fa-history"></i> Backup History</h3>
            <?php if ($latestBackup): ?>
            <div class="latest-info">
                <i class="fas fa-clock"></i> Latest: <?php echo date('M d, Y H:i:s', strtotime($latestBackup['created_at'])); ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if (empty($backups)): ?>
        <div class="empty-state">
            <i class="fas fa-database"></i>
            <h3>No backups found</h3>
            <p>Create your first backup using the form above.</p>
        </div>
        <?php else: ?>
        <?php foreach ($backups as $backup): ?>
        <div class="backup-item">
            <div class="backup-icon <?php echo $backup['backup_type']; ?>">
                <i class="fas fa-<?php echo $backup['backup_type'] === 'full' ? 'database' : ($backup['backup_type'] === 'database' ? 'table' : 'folder'); ?>"></i>
            </div>
            <div class="backup-info">
                <div class="backup-name">
                    <?php echo htmlspecialchars(basename($backup['file_path'])); ?>
                </div>
                <div class="backup-meta">
                    <span>Created: <?php echo date('M d, Y H:i:s', strtotime($backup['created_at'])); ?></span>
                    <?php if ($backup['creator_name']): ?>
                    <span class="separator">·</span>
                    <span>By: <?php echo htmlspecialchars($backup['creator_name']); ?></span>
                    <?php endif; ?>
                    <?php if ($backup['restored_at']): ?>
                    <span class="separator">·</span>
                    <span>Restored: <?php echo date('M d, Y H:i:s', strtotime($backup['restored_at'])); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="backup-size">
                <?php echo number_format($backup['file_size'] / 1024 / 1024, 2); ?> MB
            </div>
            <div>
                <span class="backup-status <?php echo $backup['status']; ?>">
                    <?php echo ucfirst($backup['status']); ?>
                </span>
            </div>
            <div class="backup-actions">
                <?php if ($backup['status'] === 'completed'): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="download_backup">
                    <input type="hidden" name="backup_id" value="<?php echo $backup['id']; ?>">
                    <button type="submit" class="btn-icon download" title="Download Backup">
                        <i class="fas fa-download"></i>
                        <span class="tooltip">Download</span>
                    </button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirmRestore();">
                    <input type="hidden" name="action" value="restore_backup">
                    <input type="hidden" name="backup_id" value="<?php echo $backup['id']; ?>">
                    <button type="submit" class="btn-icon restore" title="Restore Backup">
                        <i class="fas fa-undo"></i>
                        <span class="tooltip">Restore</span>
                    </button>
                </form>
                <?php endif; ?>
                <form method="POST" style="display:inline;" onsubmit="return confirmDeleteBackup();">
                    <input type="hidden" name="action" value="delete_backup">
                    <input type="hidden" name="backup_id" value="<?php echo $backup['id']; ?>">
                    <button type="submit" class="btn-icon delete" title="Delete Backup">
                        <i class="fas fa-trash"></i>
                        <span class="tooltip">Delete</span>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<script>
// ============================================================
// CONFIRM RESTORE
// ============================================================
function confirmRestore() {
    return confirm('⚠️ WARNING: Restoring this backup will overwrite ALL current data in the database.\n\nThis action cannot be undone. Are you sure you want to continue?');
}

// ============================================================
// CONFIRM DELETE BACKUP
// ============================================================
function confirmDeleteBackup() {
    return confirm('Delete this backup file?\n\nThis action cannot be undone.');
}

// ============================================================
// AUTO-REFRESH LATEST BACKUP INFO
// ============================================================
setInterval(function() {
    // You can implement auto-refresh here if needed
}, 30000);
</script>

<?php include 'includes/footer.php'; ?>