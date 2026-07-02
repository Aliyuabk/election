<?php
$page_title = "INEC Master Data Upload";
require_once 'includes/db.php';

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// ============================================================
// GET VALID USER ID FOR LOGGING
// ============================================================
$logUserId = getValidUserId();

// ============================================================
// HANDLE ACTIONS
// ============================================================
$message = '';
$error = '';
$message_type = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'upload_inec_data':
                // Validate file upload
                if (!isset($_FILES['inec_file']) || $_FILES['inec_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Please select a valid file to upload.");
                }
                
                $file = $_FILES['inec_file'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_exts = ['csv', 'json'];
                
                if (!in_array($file_ext, $allowed_exts)) {
                    throw new Exception("Invalid file type. Allowed: " . implode(', ', $allowed_exts));
                }
                
                // Check file size (max 20MB)
                if ($file['size'] > 20 * 1024 * 1024) {
                    throw new Exception("File too large. Maximum size is 20MB.");
                }
                
                $upload_dir = __DIR__ . '/../uploads/inec/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $filename = 'inec_data_' . date('Y-m-d_His') . '.' . $file_ext;
                $filepath = $upload_dir . $filename;
                
                if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                    throw new Exception("Failed to move uploaded file.");
                }
                
                // Process the file based on extension
                $data_type = $_POST['data_type'] ?? 'states';
                $processed = processINECFile($filepath, $file_ext, $data_type);
                
                // Log activity
                logActivity($logUserId, null, 'inec_upload', "Uploaded INEC data: " . $filename . " (" . $data_type . ")");
                
                $message = "File uploaded and processed successfully. " . $processed['message'];
                $message_type = 'success';
                break;
                
            case 'import_inec_data':
                // Direct import from JSON
                $data_type = $_POST['data_type'] ?? 'states';
                $import_data = $_POST['import_data'] ?? '';
                
                if (empty($import_data)) {
                    throw new Exception("No data to import.");
                }
                
                $result = importINECData($data_type, $import_data);
                
                logActivity($logUserId, null, 'inec_import', "Imported INEC data: " . $data_type);
                
                $message = "Data imported successfully. " . $result['message'];
                $message_type = 'success';
                break;
                
            case 'clear_data':
                $data_type = $_POST['data_type'] ?? '';
                $confirm = $_POST['confirm'] ?? '';
                
                if ($confirm !== 'yes') {
                    throw new Exception("Confirmation required to clear data.");
                }
                
                $result = clearINECData($data_type);
                
                logActivity($logUserId, null, 'inec_clear', "Cleared INEC data: " . $data_type);
                
                $message = "Data cleared successfully. " . $result['message'];
                $message_type = 'success';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        $message_type = 'error';
    }
}

// ============================================================
// PROCESS INEC FILE
// ============================================================
function processINECFile($filepath, $file_ext, $data_type) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $result = ['message' => '', 'count' => 0];
    
    // Read file based on extension
    $data = [];
    if ($file_ext === 'csv') {
        $handle = fopen($filepath, 'r');
        if ($handle) {
            $headers = fgetcsv($handle);
            if ($headers) {
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) === count($headers)) {
                        $data[] = array_combine($headers, $row);
                    }
                }
            }
            fclose($handle);
        }
    } elseif ($file_ext === 'json') {
        $json = file_get_contents($filepath);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new Exception("Invalid JSON format.");
        }
    }
    
    if (empty($data)) {
        throw new Exception("No data found in file.");
    }
    
    // Process based on data type
    switch ($data_type) {
        case 'states':
            $result = importStates($data);
            break;
        case 'lgas':
            $result = importLGAs($data);
            break;
        case 'wards':
            $result = importWards($data);
            break;
        case 'polling_units':
            $result = importPollingUnits($data);
            break;
        case 'full':
            $result = importFullData($data);
            break;
        default:
            throw new Exception("Invalid data type.");
    }
    
    return $result;
}

// ============================================================
// IMPORT FUNCTIONS
// ============================================================
function importStates($data) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $count = 0;
    $errors = [];
    
    $conn->beginTransaction();
    
    try {
        // Clear existing states (set is_active = 0)
        $conn->exec("UPDATE states SET is_active = 0");
        
        foreach ($data as $row) {
            // Map fields - support different column names
            $code = trim($row['code'] ?? $row['state_code'] ?? $row['Code'] ?? '');
            $name = trim($row['name'] ?? $row['state_name'] ?? $row['Name'] ?? '');
            $capital = trim($row['capital'] ?? $row['Capital'] ?? '');
            $lat = $row['lat'] ?? $row['gps_lat'] ?? $row['latitude'] ?? $row['Lat'] ?? null;
            $lng = $row['lng'] ?? $row['gps_lng'] ?? $row['longitude'] ?? $row['Lng'] ?? null;
            $voters = (int)($row['registered_voters'] ?? $row['voters'] ?? $row['Voters'] ?? 0);
            
            if (empty($code) || empty($name)) {
                $errors[] = "Missing code or name for state: " . json_encode($row);
                continue;
            }
            
            // Check if state exists
            $stmt = $conn->prepare("SELECT id FROM states WHERE code = ?");
            $stmt->execute([$code]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing
                $stmt = $conn->prepare("UPDATE states SET 
                    name = ?, capital = ?, gps_lat = ?, gps_lng = ?, 
                    registered_voters = ?, is_active = 1, updated_at = NOW() 
                    WHERE code = ?");
                $stmt->execute([$name, $capital, $lat, $lng, $voters, $code]);
            } else {
                // Insert new
                $stmt = $conn->prepare("INSERT INTO states (code, name, capital, gps_lat, gps_lng, registered_voters, is_active, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$code, $name, $capital, $lat, $lng, $voters]);
            }
            $count++;
        }
        
        $conn->commit();
        
        return [
            'message' => "Imported $count states. " . (count($errors) > 0 ? count($errors) . " errors." : ""),
            'count' => $count,
            'errors' => $errors
        ];
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function importLGAs($data) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $count = 0;
    $errors = [];
    
    $conn->beginTransaction();
    
    try {
        // Clear existing LGAs
        $conn->exec("UPDATE lgas SET is_active = 0");
        
        foreach ($data as $row) {
            $state_code = trim($row['state_code'] ?? $row['StateCode'] ?? $row['state'] ?? '');
            $code = trim($row['code'] ?? $row['lga_code'] ?? $row['Code'] ?? '');
            $name = trim($row['name'] ?? $row['lga_name'] ?? $row['Name'] ?? '');
            $lat = $row['lat'] ?? $row['gps_lat'] ?? $row['latitude'] ?? null;
            $lng = $row['lng'] ?? $row['gps_lng'] ?? $row['longitude'] ?? null;
            $voters = (int)($row['registered_voters'] ?? $row['voters'] ?? 0);
            
            if (empty($state_code) || empty($code) || empty($name)) {
                $errors[] = "Missing state_code, code, or name: " . json_encode($row);
                continue;
            }
            
            // Get state ID
            $stmt = $conn->prepare("SELECT id FROM states WHERE code = ? AND is_active = 1");
            $stmt->execute([$state_code]);
            $state = $stmt->fetch();
            
            if (!$state) {
                $errors[] = "State not found: " . $state_code;
                continue;
            }
            
            // Check if LGA exists
            $stmt = $conn->prepare("SELECT id FROM lgas WHERE state_id = ? AND code = ?");
            $stmt->execute([$state['id'], $code]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $stmt = $conn->prepare("UPDATE lgas SET 
                    name = ?, gps_lat = ?, gps_lng = ?, 
                    registered_voters = ?, is_active = 1 
                    WHERE state_id = ? AND code = ?");
                $stmt->execute([$name, $lat, $lng, $voters, $state['id'], $code]);
            } else {
                $stmt = $conn->prepare("INSERT INTO lgas (state_id, code, name, gps_lat, gps_lng, registered_voters, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$state['id'], $code, $name, $lat, $lng, $voters]);
            }
            $count++;
        }
        
        $conn->commit();
        
        return [
            'message' => "Imported $count LGAs. " . (count($errors) > 0 ? count($errors) . " errors." : ""),
            'count' => $count,
            'errors' => $errors
        ];
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function importWards($data) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $count = 0;
    $errors = [];
    
    $conn->beginTransaction();
    
    try {
        // Clear existing wards
        $conn->exec("UPDATE wards SET is_active = 0");
        
        foreach ($data as $row) {
            $lga_code = trim($row['lga_code'] ?? $row['LgaCode'] ?? $row['lga'] ?? '');
            $code = trim($row['code'] ?? $row['ward_code'] ?? $row['Code'] ?? '');
            $name = trim($row['name'] ?? $row['ward_name'] ?? $row['Name'] ?? '');
            $lat = $row['lat'] ?? $row['gps_lat'] ?? null;
            $lng = $row['lng'] ?? $row['gps_lng'] ?? null;
            $voters = (int)($row['registered_voters'] ?? $row['voters'] ?? 0);
            
            if (empty($lga_code) || empty($code) || empty($name)) {
                $errors[] = "Missing lga_code, code, or name: " . json_encode($row);
                continue;
            }
            
            // Get LGA ID
            $stmt = $conn->prepare("SELECT id FROM lgas WHERE code = ? AND is_active = 1");
            $stmt->execute([$lga_code]);
            $lga = $stmt->fetch();
            
            if (!$lga) {
                $errors[] = "LGA not found: " . $lga_code;
                continue;
            }
            
            // Check if ward exists
            $stmt = $conn->prepare("SELECT id FROM wards WHERE lga_id = ? AND code = ?");
            $stmt->execute([$lga['id'], $code]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $stmt = $conn->prepare("UPDATE wards SET 
                    name = ?, gps_lat = ?, gps_lng = ?, 
                    registered_voters = ?, is_active = 1 
                    WHERE lga_id = ? AND code = ?");
                $stmt->execute([$name, $lat, $lng, $voters, $lga['id'], $code]);
            } else {
                $stmt = $conn->prepare("INSERT INTO wards (lga_id, code, name, gps_lat, gps_lng, registered_voters, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$lga['id'], $code, $name, $lat, $lng, $voters]);
            }
            $count++;
        }
        
        $conn->commit();
        
        return [
            'message' => "Imported $count Wards. " . (count($errors) > 0 ? count($errors) . " errors." : ""),
            'count' => $count,
            'errors' => $errors
        ];
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function importPollingUnits($data) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $count = 0;
    $errors = [];
    
    $conn->beginTransaction();
    
    try {
        // Clear existing polling units
        $conn->exec("UPDATE polling_units SET is_active = 0");
        
        foreach ($data as $row) {
            $ward_code = trim($row['ward_code'] ?? $row['WardCode'] ?? $row['ward'] ?? '');
            $code = trim($row['code'] ?? $row['pu_code'] ?? $row['Code'] ?? '');
            $name = trim($row['name'] ?? $row['pu_name'] ?? $row['Name'] ?? '');
            $lat = $row['lat'] ?? $row['gps_lat'] ?? null;
            $lng = $row['lng'] ?? $row['gps_lng'] ?? null;
            $voters = (int)($row['registered_voters'] ?? $row['voters'] ?? 0);
            $address = trim($row['address'] ?? $row['Address'] ?? '');
            
            if (empty($ward_code) || empty($code) || empty($name)) {
                $errors[] = "Missing ward_code, code, or name: " . json_encode($row);
                continue;
            }
            
            // Get Ward ID
            $stmt = $conn->prepare("SELECT id FROM wards WHERE code = ? AND is_active = 1");
            $stmt->execute([$ward_code]);
            $ward = $stmt->fetch();
            
            if (!$ward) {
                $errors[] = "Ward not found: " . $ward_code;
                continue;
            }
            
            // Check if polling unit exists
            $stmt = $conn->prepare("SELECT id FROM polling_units WHERE ward_id = ? AND code = ?");
            $stmt->execute([$ward['id'], $code]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $stmt = $conn->prepare("UPDATE polling_units SET 
                    name = ?, gps_lat = ?, gps_lng = ?, 
                    registered_voters = ?, address = ?, is_active = 1 
                    WHERE ward_id = ? AND code = ?");
                $stmt->execute([$name, $lat, $lng, $voters, $address, $ward['id'], $code]);
            } else {
                $stmt = $conn->prepare("INSERT INTO polling_units (ward_id, code, name, gps_lat, gps_lng, registered_voters, address, is_active, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$ward['id'], $code, $name, $lat, $lng, $voters, $address]);
            }
            $count++;
        }
        
        $conn->commit();
        
        return [
            'message' => "Imported $count Polling Units. " . (count($errors) > 0 ? count($errors) . " errors." : ""),
            'count' => $count,
            'errors' => $errors
        ];
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function importFullData($data) {
    // Import all data types
    $results = [];
    
    if (isset($data['states'])) {
        $results['states'] = importStates($data['states']);
    }
    if (isset($data['lgas'])) {
        $results['lgas'] = importLGAs($data['lgas']);
    }
    if (isset($data['wards'])) {
        $results['wards'] = importWards($data['wards']);
    }
    if (isset($data['polling_units'])) {
        $results['polling_units'] = importPollingUnits($data['polling_units']);
    }
    
    $total = 0;
    $msg = [];
    foreach ($results as $key => $result) {
        $total += $result['count'];
        $msg[] = ucfirst(str_replace('_', ' ', $key)) . ": " . $result['count'];
    }
    
    return [
        'message' => "Imported " . $total . " records total. " . implode(", ", $msg),
        'count' => $total,
        'details' => $results
    ];
}

function importINECData($data_type, $import_data) {
    // Parse JSON data
    $data = json_decode($import_data, true);
    if (!$data) {
        throw new Exception("Invalid JSON data.");
    }
    
    switch ($data_type) {
        case 'states':
            return importStates($data);
        case 'lgas':
            return importLGAs($data);
        case 'wards':
            return importWards($data);
        case 'polling_units':
            return importPollingUnits($data);
        default:
            throw new Exception("Invalid data type.");
    }
}

function clearINECData($data_type) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $count = 0;
    
    switch ($data_type) {
        case 'states':
            $stmt = $conn->prepare("UPDATE states SET is_active = 0");
            $stmt->execute();
            $count = $stmt->rowCount();
            break;
        case 'lgas':
            $stmt = $conn->prepare("UPDATE lgas SET is_active = 0");
            $stmt->execute();
            $count = $stmt->rowCount();
            break;
        case 'wards':
            $stmt = $conn->prepare("UPDATE wards SET is_active = 0");
            $stmt->execute();
            $count = $stmt->rowCount();
            break;
        case 'polling_units':
            $stmt = $conn->prepare("UPDATE polling_units SET is_active = 0");
            $stmt->execute();
            $count = $stmt->rowCount();
            break;
        case 'all':
            $conn->exec("UPDATE states SET is_active = 0");
            $conn->exec("UPDATE lgas SET is_active = 0");
            $conn->exec("UPDATE wards SET is_active = 0");
            $conn->exec("UPDATE polling_units SET is_active = 0");
            $count = "all";
            break;
        default:
            throw new Exception("Invalid data type.");
    }
    
    return [
        'message' => "Cleared $data_type data.",
        'count' => $count
    ];
}

// ============================================================
// GET STATISTICS
// ============================================================
$stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM states WHERE is_active = 1) as states,
        (SELECT COUNT(*) FROM lgas WHERE is_active = 1) as lgas,
        (SELECT COUNT(*) FROM wards WHERE is_active = 1) as wards,
        (SELECT COUNT(*) FROM polling_units WHERE is_active = 1) as polling_units,
        (SELECT COUNT(*) FROM states) as total_states,
        (SELECT COUNT(*) FROM lgas) as total_lgas,
        (SELECT COUNT(*) FROM wards) as total_wards,
        (SELECT COUNT(*) FROM polling_units) as total_polling_units,
        (SELECT COUNT(*) FROM states WHERE is_active = 0) as inactive_states
")->fetch();

// Get recent uploads
$recentUploads = $conn->query("
    SELECT * FROM activity_logs 
    WHERE activity_type IN ('inec_upload', 'inec_import', 'inec_clear')
    ORDER BY created_at DESC 
    LIMIT 10
")->fetchAll();

include 'includes/base.php';
?>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/header.php'; ?>

<style>
/* ============================================================
   INEC UPLOAD STYLES
   ============================================================ */

/* Stats Grid */
.inec-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.inec-stats .stat-card {
    background: white;
    border-radius: 14px;
    padding: 16px 20px;
    text-align: center;
    border: 1px solid #eef3f8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    transition: all 0.2s ease;
}

.inec-stats .stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
}

.inec-stats .stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: #0b1a33;
    line-height: 1.2;
}

.inec-stats .stat-label {
    font-size: 0.7rem;
    color: #6d83a5;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-top: 4px;
}

.inec-stats .stat-icon {
    font-size: 1.5rem;
    display: block;
    margin-bottom: 8px;
}

.stat-icon.states { color: #4f9cf7; }
.stat-icon.lgas { color: #10b981; }
.stat-icon.wards { color: #f59e0b; }
.stat-icon.pu { color: #8b5cf6; }

/* Upload Area */
.upload-area {
    border: 2px dashed #dce6f0;
    border-radius: 14px;
    padding: 40px 20px;
    text-align: center;
    transition: all 0.3s ease;
    background: #fafcff;
    cursor: pointer;
    margin-bottom: 24px;
}

.upload-area:hover {
    border-color: #4f9cf7;
    background: #f8faff;
}

.upload-area.dragover {
    border-color: #4f9cf7;
    background: #e8f0fe;
}

.upload-area i {
    font-size: 3rem;
    color: #8b9bb5;
    display: block;
    margin-bottom: 12px;
}

.upload-area h3 {
    font-size: 1.1rem;
    color: #1f3149;
    margin-bottom: 4px;
}

.upload-area p {
    color: #8b9bb5;
    font-size: 0.9rem;
}

.upload-area small {
    display: block;
    color: #a0b8d4;
    font-size: 0.75rem;
    margin-top: 4px;
}

.upload-area .file-input {
    display: none;
}

/* Upload Progress */
.upload-progress {
    display: none;
    margin-top: 16px;
}

.upload-progress.active {
    display: block;
}

.upload-progress .progress-bar {
    width: 100%;
    height: 6px;
    background: #eef3f8;
    border-radius: 10px;
    overflow: hidden;
    margin-top: 8px;
}

.upload-progress .progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #4f9cf7, #3b82d6);
    border-radius: 10px;
    width: 0%;
    transition: width 0.3s ease;
}

.upload-progress .progress-text {
    font-size: 0.85rem;
    color: #6d83a5;
    margin-top: 4px;
}

/* Upload Options */
.upload-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
}

.upload-options .option-card {
    background: white;
    border-radius: 14px;
    padding: 20px;
    border: 1px solid #eef3f8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}

.upload-options .option-card h4 {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1f3149;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.upload-options .option-card h4 i {
    color: #4f9cf7;
}

.upload-options .form-group {
    margin-bottom: 12px;
}

.upload-options .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 500;
    color: #1f3149;
    margin-bottom: 4px;
}

.upload-options .form-group select,
.upload-options .form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #dce6f0;
    border-radius: 10px;
    font-size: 0.9rem;
    background: white;
    color: #1f3149;
    transition: 0.15s;
}

.upload-options .form-group select:focus,
.upload-options .form-group textarea:focus {
    outline: none;
    border-color: #4f9cf7;
    box-shadow: 0 0 0 3px rgba(79, 156, 247, 0.1);
}

.upload-options .form-group textarea {
    resize: vertical;
    min-height: 100px;
    font-family: monospace;
}

.upload-options .form-group small {
    display: block;
    font-size: 0.7rem;
    color: #8b9bb5;
    margin-top: 4px;
}

/* Data Type Badge */
.data-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.7rem;
    padding: 2px 12px;
    border-radius: 30px;
    font-weight: 500;
}

.data-type-badge.inec_upload { background: #dbeafe; color: #1e40af; }
.data-type-badge.inec_import { background: #d1fae5; color: #065f46; }
.data-type-badge.inec_clear { background: #fee2e2; color: #991b1b; }

/* Danger Zone */
.danger-zone {
    background: white;
    border-radius: 14px;
    padding: 20px;
    border: 1px solid #fee2e2;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    margin-bottom: 24px;
}

.danger-zone h4 {
    font-size: 0.95rem;
    font-weight: 600;
    color: #ef4444;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.danger-zone .danger-controls {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.danger-zone .danger-controls select {
    flex: 1;
    min-width: 150px;
    padding: 10px 14px;
    border: 1px solid #dce6f0;
    border-radius: 10px;
    font-size: 0.9rem;
    background: white;
    color: #1f3149;
}

.btn-danger {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
    padding: 10px 24px;
    border-radius: 10px;
    font-weight: 500;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.15s;
}

.btn-danger:hover {
    background: #fecaca;
    transform: translateY(-1px);
}

.danger-zone small {
    display: block;
    color: #ef4444;
    font-size: 0.75rem;
    margin-top: 8px;
}

/* Recent Uploads */
.recent-uploads {
    background: white;
    border-radius: 14px;
    padding: 20px;
    border: 1px solid #eef3f8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}

.recent-uploads h3 {
    font-size: 1rem;
    font-weight: 600;
    color: #1f3149;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.recent-uploads h3 i {
    color: #4f9cf7;
}

.upload-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 10px 0;
    border-bottom: 1px solid #f5f8fc;
}

.upload-item:last-child {
    border-bottom: none;
}

.upload-item .upload-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.upload-item .upload-icon.upload { background: #dbeafe; color: #4f9cf7; }
.upload-item .upload-icon.import { background: #d1fae5; color: #10b981; }
.upload-item .upload-icon.clear { background: #fee2e2; color: #ef4444; }

.upload-item .upload-info {
    flex: 1;
}

.upload-item .upload-title {
    font-weight: 500;
    color: #0b1a33;
    font-size: 0.9rem;
}

.upload-item .upload-meta {
    font-size: 0.75rem;
    color: #8b9bb5;
}

.upload-item .upload-time {
    font-size: 0.7rem;
    color: #8b9bb5;
    white-space: nowrap;
}

/* Template Helpers */
.template-helpers {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 4px;
}

.template-helpers button {
    padding: 2px 12px;
    border: 1px solid #dce6f0;
    border-radius: 30px;
    background: white;
    font-size: 0.7rem;
    color: #6d83a5;
    cursor: pointer;
    transition: 0.15s;
}

.template-helpers button:hover {
    border-color: #4f9cf7;
    color: #4f9cf7;
    background: #f8faff;
}

/* Responsive */
@media (max-width: 768px) {
    .upload-options {
        grid-template-columns: 1fr;
    }
    
    .inec-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .danger-zone .danger-controls {
        flex-direction: column;
    }
    
    .danger-zone .danger-controls select {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .inec-stats {
        grid-template-columns: 1fr 1fr;
    }
    
    .upload-area {
        padding: 24px 16px;
    }
    
    .upload-area i {
        font-size: 2rem;
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
                <i class="fas fa-upload" style="color:#f59e0b;"></i>
                INEC Master Data Upload
                <span class="page-badge">Import</span>
            </h1>
            <p class="subtitle">Upload and manage INEC election data including states, LGAs, wards, and polling units</p>
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
    <div class="inec-stats">
        <div class="stat-card">
            <span class="stat-icon states"><i class="fas fa-flag"></i></span>
            <div class="stat-number"><?php echo number_format($stats['states'] ?? 0); ?></div>
            <div class="stat-label">Active States</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon lgas"><i class="fas fa-city"></i></span>
            <div class="stat-number"><?php echo number_format($stats['lgas'] ?? 0); ?></div>
            <div class="stat-label">Active LGAs</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon wards"><i class="fas fa-layer-group"></i></span>
            <div class="stat-number"><?php echo number_format($stats['wards'] ?? 0); ?></div>
            <div class="stat-label">Active Wards</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon pu"><i class="fas fa-map-pin"></i></span>
            <div class="stat-number"><?php echo number_format($stats['polling_units'] ?? 0); ?></div>
            <div class="stat-label">Active Polling Units</div>
        </div>
    </div>

    <!-- ============================================================
    UPLOAD OPTIONS
    ============================================================ -->
    <div class="upload-options">
        <!-- File Upload -->
        <div class="option-card">
            <h4><i class="fas fa-file-upload"></i> Upload File</h4>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="action" value="upload_inec_data">
                
                <div class="upload-area" id="dropZone">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <h3>Drop your file here</h3>
                    <p>or click to browse</p>
                    <small>Supported: CSV, JSON (Max 20MB)</small>
                    <input type="file" name="inec_file" id="fileInput" class="file-input" accept=".csv,.json">
                </div>
                
                <div class="upload-progress" id="uploadProgress">
                    <div class="progress-text">Uploading...</div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="data_type">Data Type</label>
                    <select name="data_type" id="data_type" required>
                        <option value="states">States</option>
                        <option value="lgas">Local Government Areas (LGAs)</option>
                        <option value="wards">Wards</option>
                        <option value="polling_units">Polling Units</option>
                        <option value="full">Full Data (All Types)</option>
                    </select>
                    <small>Select the type of data you are uploading</small>
                </div>
                
                <button type="submit" class="btn-primary" style="width:100%; justify-content:center;">
                    <i class="fas fa-upload"></i> Upload and Process
                </button>
            </form>
        </div>

        <!-- Direct Import -->
        <div class="option-card">
            <h4><i class="fas fa-code"></i> Direct Import</h4>
            <form method="POST" id="importForm">
                <input type="hidden" name="action" value="import_inec_data">
                
                <div class="form-group">
                    <label for="import_data_type">Data Type</label>
                    <select name="data_type" id="import_data_type" required>
                        <option value="states">States</option>
                        <option value="lgas">Local Government Areas (LGAs)</option>
                        <option value="wards">Wards</option>
                        <option value="polling_units">Polling Units</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="import_data">JSON Data</label>
                    <textarea name="import_data" id="import_data" placeholder='[{"code":"LA","name":"Lagos","capital":"Ikeja","gps_lat":6.5244,"gps_lng":3.3792,"registered_voters":5931571}]'></textarea>
                    <small>Paste valid JSON data matching the selected type</small>
                </div>
                
                <div class="template-helpers">
                    <button type="button" onclick="loadTemplate('states')">States Template</button>
                    <button type="button" onclick="loadTemplate('lgas')">LGAs Template</button>
                    <button type="button" onclick="loadTemplate('wards')">Wards Template</button>
                    <button type="button" onclick="loadTemplate('polling_units')">Polling Units Template</button>
                </div>
                
                <button type="submit" class="btn-primary" style="width:100%; justify-content:center; margin-top:12px;">
                    <i class="fas fa-database"></i> Import Data
                </button>
            </form>
        </div>
    </div>

    <!-- ============================================================
    DANGER ZONE - Clear Data
    ============================================================ -->
    <div class="danger-zone">
        <h4><i class="fas fa-exclamation-triangle"></i> Danger Zone</h4>
        <form method="POST" onsubmit="return confirmClearData();">
            <input type="hidden" name="action" value="clear_data">
            <input type="hidden" name="confirm" value="yes">
            
            <div class="danger-controls">
                <select name="data_type" id="clear_data_type" required>
                    <option value="states">Clear States</option>
                    <option value="lgas">Clear LGAs</option>
                    <option value="wards">Clear Wards</option>
                    <option value="polling_units">Clear Polling Units</option>
                    <option value="all">Clear All Data</option>
                </select>
                <button type="submit" class="btn-danger">
                    <i class="fas fa-trash"></i> Clear Data
                </button>
            </div>
            <small>
                <i class="fas fa-exclamation-circle"></i> This action cannot be undone. All selected data will be deactivated.
            </small>
        </form>
    </div>

    <!-- ============================================================
    RECENT UPLOADS
    ============================================================ -->
    <div class="recent-uploads">
        <h3><i class="fas fa-history"></i> Recent Uploads & Activity</h3>
        <?php if (empty($recentUploads)): ?>
        <p style="color:#8b9bb5; text-align:center; padding:20px;">
            <i class="fas fa-inbox" style="font-size:2rem; display:block; margin-bottom:8px;"></i>
            No recent uploads
        </p>
        <?php else: ?>
        <?php foreach ($recentUploads as $upload): ?>
        <div class="upload-item">
            <div class="upload-icon <?php echo $upload['activity_type'] === 'inec_upload' ? 'upload' : ($upload['activity_type'] === 'inec_import' ? 'import' : 'clear'); ?>">
                <i class="fas fa-<?php echo $upload['activity_type'] === 'inec_upload' ? 'file-upload' : ($upload['activity_type'] === 'inec_import' ? 'database' : 'trash'); ?>"></i>
            </div>
            <div class="upload-info">
                <div class="upload-title"><?php echo htmlspecialchars($upload['description']); ?></div>
                <div class="upload-meta">
                    <span class="data-type-badge <?php echo $upload['activity_type']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $upload['activity_type'])); ?>
                    </span>
                </div>
            </div>
            <div class="upload-time"><?php echo date('M d, Y H:i', strtotime($upload['created_at'])); ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<!-- ============================================================
JAVASCRIPT
============================================================ -->
<script>
// ============================================================
// FILE UPLOAD HANDLING
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const uploadForm = document.getElementById('uploadForm');
    const progress = document.getElementById('uploadProgress');
    const progressFill = document.getElementById('progressFill');
    
    // Click to browse
    dropZone.addEventListener('click', function() {
        fileInput.click();
    });
    
    // File selected
    fileInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const file = this.files[0];
            dropZone.querySelector('h3').textContent = file.name;
            dropZone.querySelector('p').textContent = (file.size / 1024).toFixed(1) + ' KB';
            dropZone.style.borderColor = '#10b981';
            dropZone.style.background = '#d1fae5';
        }
    });
    
    // Drag and drop
    dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });
    
    dropZone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
    });
    
    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            fileInput.files = e.dataTransfer.files;
            const file = e.dataTransfer.files[0];
            dropZone.querySelector('h3').textContent = file.name;
            dropZone.querySelector('p').textContent = (file.size / 1024).toFixed(1) + ' KB';
            dropZone.style.borderColor = '#10b981';
            dropZone.style.background = '#d1fae5';
        }
    });
    
    // Form submit with progress
    uploadForm.addEventListener('submit', function(e) {
        if (!fileInput.files || !fileInput.files[0]) {
            e.preventDefault();
            alert('Please select a file to upload.');
            return;
        }
        
        progress.classList.add('active');
        progressFill.style.width = '50%';
        
        // Simulate progress
        let progressValue = 50;
        const interval = setInterval(function() {
            progressValue += Math.random() * 10;
            if (progressValue > 95) {
                progressValue = 95;
                clearInterval(interval);
            }
            progressFill.style.width = progressValue + '%';
        }, 200);
    });
});

// ============================================================
// CONFIRM CLEAR DATA
// ============================================================
function confirmClearData() {
    const dataType = document.getElementById('clear_data_type').value;
    const typeLabels = {
        'states': 'States',
        'lgas': 'Local Government Areas',
        'wards': 'Wards',
        'polling_units': 'Polling Units',
        'all': 'ALL DATA'
    };
    
    return confirm(`⚠️ Are you sure you want to clear ${typeLabels[dataType] || dataType}?\n\nThis action cannot be undone. All selected data will be deactivated.`);
}

// ============================================================
// JSON VALIDATION
// ============================================================
document.getElementById('importForm')?.addEventListener('submit', function(e) {
    const jsonData = document.getElementById('import_data').value;
    if (jsonData) {
        try {
            JSON.parse(jsonData);
        } catch (error) {
            e.preventDefault();
            alert('Invalid JSON format. Please check your data.\n\n' + error.message);
        }
    }
});

// ============================================================
// TEMPLATE HELPERS
// ============================================================
function loadTemplate(dataType) {
    const templates = {
        'states': JSON.stringify([
            {
                "code": "LA",
                "name": "Lagos",
                "capital": "Ikeja",
                "gps_lat": 6.5244,
                "gps_lng": 3.3792,
                "registered_voters": 5931571
            },
            {
                "code": "FC",
                "name": "Federal Capital Territory",
                "capital": "Abuja",
                "gps_lat": 9.0667,
                "gps_lng": 7.4833,
                "registered_voters": 1602702
            }
        ], null, 2),
        'lgas': JSON.stringify([
            {
                "state_code": "LA",
                "code": "LA001",
                "name": "Agege",
                "gps_lat": 6.6167,
                "gps_lng": 3.3333,
                "registered_voters": 226934
            },
            {
                "state_code": "LA",
                "code": "LA002",
                "name": "Ajeromi-Ifelodun",
                "gps_lat": 6.45,
                "gps_lng": 3.3333,
                "registered_voters": 361380
            }
        ], null, 2),
        'wards': JSON.stringify([
            {
                "lga_code": "LA001",
                "code": "WARD001",
                "name": "Ward 1",
                "registered_voters": 15000
            },
            {
                "lga_code": "LA001",
                "code": "WARD002",
                "name": "Ward 2",
                "registered_voters": 12000
            }
        ], null, 2),
        'polling_units': JSON.stringify([
            {
                "ward_code": "WARD001",
                "code": "PU001",
                "name": "Polling Unit 1",
                "address": "School 1",
                "registered_voters": 500
            },
            {
                "ward_code": "WARD001",
                "code": "PU002",
                "name": "Polling Unit 2",
                "address": "School 2",
                "registered_voters": 450
            }
        ], null, 2)
    };
    
    document.getElementById('import_data').value = templates[dataType] || '';
    document.getElementById('import_data_type').value = dataType;
    
    // Highlight the textarea
    const textarea = document.getElementById('import_data');
    textarea.style.borderColor = '#4f9cf7';
    textarea.style.boxShadow = '0 0 0 3px rgba(79, 156, 247, 0.1)';
    setTimeout(() => {
        textarea.style.borderColor = '#dce6f0';
        textarea.style.boxShadow = 'none';
    }, 2000);
}
</script>

<?php include 'includes/footer.php'; ?>