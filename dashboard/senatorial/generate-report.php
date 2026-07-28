<?php
// ============================================================
// SENATORIAL COORDINATOR - GENERATE REPORT
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'senatorial') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$senatorial_id = SessionManager::get('senatorial_id');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// GET SENATORIAL DISTRICT NAME
// ============================================================
$district_name = 'Senatorial District';
$state_name = 'State';
try {
    if ($senatorial_id) {
        $stmt = $db->prepare("
            SELECT s.name as state_name, sd.name as district_name 
            FROM senatorial_districts sd 
            JOIN states s ON sd.state_id = s.id 
            WHERE sd.id = ?
        ");
        $stmt->execute([$senatorial_id]);
        $result = $stmt->fetch();
        if ($result) {
            $district_name = $result['district_name'];
            $state_name = $result['state_name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching district: " . $e->getMessage());
}

// ============================================================
// GET LGA IDs FROM SENATORIAL DISTRICT
// ============================================================
$lga_ids = [];
try {
    if ($senatorial_id) {
        $stmt = $db->prepare("SELECT lgas_json FROM senatorial_districts WHERE id = ?");
        $stmt->execute([$senatorial_id]);
        $lgas_json = $stmt->fetchColumn();
        
        if ($lgas_json) {
            $lga_names = json_decode($lgas_json, true) ?: [];
            
            if (!empty($lga_names)) {
                $placeholders = implode(',', array_fill(0, count($lga_names), '?'));
                $stmt = $db->prepare("SELECT id FROM lgas WHERE name IN ($placeholders) AND state_id = ? AND is_active = 1");
                $stmt->execute(array_merge($lga_names, [$state_id]));
                $lga_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        }
    }
} catch (Exception $e) {
    error_log("Error getting LGA IDs: " . $e->getMessage());
    $lga_ids = [];
}

$lga_list = !empty($lga_ids) ? implode(',', array_map('intval', $lga_ids)) : '0';

// ============================================================
// GET LGAS AND ELECTIONS FOR FILTERS
// ============================================================
$lgas = [];
$elections = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE id IN ($lga_list) AND is_active = 1 ORDER BY name ASC");
        $stmt->execute();
        $lgas = $stmt->fetchAll();
    }
    
    $stmt = $db->prepare("SELECT id, name, type, election_date FROM elections WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY election_date DESC");
    $stmt->execute([$tenant_id]);
    $elections = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching filters: " . $e->getMessage());
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$error = '';
$success = '';
$report_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_type = $_POST['report_type'] ?? '';
    $report_format = $_POST['format'] ?? 'pdf';
    $election_id = isset($_POST['election_id']) ? (int)$_POST['election_id'] : 0;
    $lga_id = isset($_POST['lga_id']) ? (int)$_POST['lga_id'] : 0;
    $ward_id = isset($_POST['ward_id']) ? (int)$_POST['ward_id'] : 0;
    $report_name = trim($_POST['report_name'] ?? '');
    $schedule = isset($_POST['schedule']) ? true : false;
    $schedule_frequency = $_POST['schedule_frequency'] ?? 'daily';
    
    // Store for repopulation
    $report_data = [
        'report_type' => $report_type,
        'format' => $report_format,
        'election_id' => $election_id,
        'lga_id' => $lga_id,
        'ward_id' => $ward_id,
        'report_name' => $report_name,
        'schedule' => $schedule,
        'schedule_frequency' => $schedule_frequency
    ];
    
    $errors = [];
    
    if (empty($report_type)) $errors[] = 'Report type is required.';
    if (empty($report_name)) $errors[] = 'Report name is required.';
    if (empty($election_id)) $errors[] = 'Election is required.';
    
    if (empty($errors)) {
        try {
            // Generate report file
            $file_url = generateReportFile($report_type, $report_format, $election_id, $lga_id, $ward_id, $tenant_id);
            
            if ($file_url) {
                // Save report record
                $stmt = $db->prepare("
                    INSERT INTO reports (
                        tenant_id, election_id, name, type, format,
                        filters_json, file_url, file_size, generated_by,
                        is_scheduled, schedule_cron, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $filters_json = json_encode([
                    'election_id' => $election_id,
                    'lga_id' => $lga_id,
                    'ward_id' => $ward_id
                ]);
                
                $file_size = file_exists($file_url) ? filesize($file_url) : 0;
                $is_scheduled = $schedule ? 1 : 0;
                $schedule_cron = $schedule ? $schedule_frequency : null;
                
                $stmt->execute([
                    $tenant_id,
                    $election_id,
                    $report_name,
                    $report_type,
                    $report_format,
                    $filters_json,
                    $file_url,
                    $file_size,
                    $user_id,
                    $is_scheduled,
                    $schedule_cron
                ]);
                
                $report_id = $db->lastInsertId();
                
                logActivity($user_id, 'report_generated', "Generated report: $report_name (ID: $report_id)");
                
                $success = "Report generated successfully!";
                
                // Clear form data on success
                $report_data = [];
            } else {
                $error = 'Failed to generate report file.';
            }
        } catch (Exception $e) {
            $error = 'Error generating report: ' . $e->getMessage();
            error_log("Report generation error: " . $e->getMessage());
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// ============================================================
// FUNCTION TO GENERATE REPORT FILE
// ============================================================
function generateReportFile($type, $format, $election_id, $lga_id, $ward_id, $tenant_id) {
    // This is a placeholder - in production, you would use a library
    // like Dompdf, PhpSpreadsheet, or TCPDF to generate actual files
    
    // For now, create a dummy file
    $filename = 'report_' . $type . '_' . date('Y-m-d_H-i-s') . '.' . ($format === 'excel' ? 'xlsx' : ($format === 'csv' ? 'csv' : 'pdf'));
    $filepath = '../../uploads/reports/' . $filename;
    
    // Ensure directory exists
    if (!is_dir('../../uploads/reports/')) {
        mkdir('../../uploads/reports/', 0777, true);
    }
    
    // Create dummy content
    $content = "Report: $type\n";
    $content .= "Generated: " . date('Y-m-d H:i:s') . "\n";
    $content .= "Election ID: $election_id\n";
    $content .= "LGA ID: $lga_id\n";
    $content .= "Ward ID: $ward_id\n";
    $content .= "Format: $format\n";
    $content .= "Tenant: $tenant_id\n";
    $content .= "\nThis is a sample report. In production, this would contain actual data.\n";
    
    file_put_contents($filepath, $content);
    
    return $filepath;
}

// ============================================================
// GET WARDS FOR FILTER (if LGA is selected)
// ============================================================
$wards = [];
if (isset($_GET['lga_id']) && $_GET['lga_id'] > 0) {
    try {
        $stmt = $db->prepare("SELECT id, name FROM wards WHERE lga_id = ? AND is_active = 1 ORDER BY name ASC");
        $stmt->execute([(int)$_GET['lga_id']]);
        $wards = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching wards: " . $e->getMessage());
    }
}

$page_title = 'Generate Report';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
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

.form-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 28px 32px;
    box-shadow: var(--shadow);
}
.form-container .form-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.form-container .form-subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--gray-100);
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px 24px;
}
.form-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.form-group.full-width {
    grid-column: 1 / -1;
}
.form-group label {
    font-weight: 600;
    font-size: 0.82rem;
    color: var(--gray-700);
}
.form-group label .required {
    color: var(--danger);
    margin-left: 2px;
}
.form-group .help-text {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 2px;
}
.form-group input,
.form-group select {
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
.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.form-section-title {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--gray-700);
    grid-column: 1 / -1;
    padding-top: 8px;
    border-bottom: 1px solid var(--gray-100);
    padding-bottom: 8px;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.form-section-title i {
    color: var(--primary);
    font-size: 0.85rem;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--gray-200);
    flex-wrap: wrap;
}
.form-actions .btn {
    padding: 10px 28px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.form-actions .btn-primary {
    background: var(--primary);
    color: white;
}
.form-actions .btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25);
}
.form-actions .btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.form-actions .btn-secondary:hover {
    background: var(--gray-200);
}

.alert {
    padding: 14px 18px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    border: 1px solid transparent;
}
.alert i {
    margin-top: 2px;
    font-size: 1.1rem;
}
.alert-success {
    background: #ECFDF5;
    color: #065F46;
    border-color: #A7F3D0;
}
.alert-error {
    background: #FEF2F2;
    color: #DC2626;
    border-color: #FECACA;
}

.report-type-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}
.report-type-option {
    padding: 10px 14px;
    border: 2px solid var(--gray-200);
    border-radius: 10px;
    cursor: pointer;
    text-align: center;
    transition: var(--transition);
}
.report-type-option:hover {
    border-color: var(--primary);
}
.report-type-option input[type="radio"] {
    display: none;
}
.report-type-option.selected {
    border-color: var(--primary);
    background: rgba(37, 99, 235, 0.04);
}
.report-type-option .icon {
    font-size: 1.2rem;
    display: block;
    margin-bottom: 4px;
}
.report-type-option .label {
    font-size: 0.8rem;
    font-weight: 500;
}

.switch-container {
    display: flex;
    align-items: center;
    gap: 12px;
}
.switch {
    position: relative;
    width: 48px;
    height: 26px;
    flex-shrink: 0;
}
.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.switch .slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--gray-300);
    transition: .3s;
    border-radius: 26px;
}
.switch .slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background: white;
    transition: .3s;
    border-radius: 50%;
}
.switch input:checked + .slider {
    background: var(--primary);
}
.switch input:checked + .slider:before {
    transform: translateX(22px);
}
.switch-label {
    font-size: 0.85rem;
    color: var(--gray-600);
}

.hidden {
    display: none !important;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .form-container {
        padding: 20px;
    }
    .form-actions {
        flex-direction: column;
    }
    .form-actions .btn {
        justify-content: center;
        width: 100%;
    }
    .report-type-grid {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-plus" style="color:var(--primary);margin-right:8px;"></i> 
                    Generate Report
                    <small><?php echo htmlspecialchars($district_name); ?> - <?php echo htmlspecialchars($state_name); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="reports.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-arrow-left"></i> Back to Reports
                </a>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $success; ?></div>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="form-container">
            <div class="form-title">
                <i class="fas fa-file-alt"></i> New Report
            </div>
            <div class="form-subtitle">
                Fill in the details below to generate a new report.
            </div>
            
            <form method="POST" action="" id="reportForm">
                <div class="form-grid">
                    <!-- Report Type -->
                    <div class="form-section-title">
                        <i class="fas fa-cog"></i> Report Configuration
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Report Type <span class="required">*</span></label>
                        <div class="report-type-grid">
                            <?php 
                            $report_types = [
                                'progress' => ['icon' => 'fa-chart-line', 'label' => 'Election Progress'],
                                'constituency' => ['icon' => 'fa-building', 'label' => 'Federal Constituency'],
                                'lga' => ['icon' => 'fa-map-marker-alt', 'label' => 'LGA'],
                                'ward' => ['icon' => 'fa-layer-group', 'label' => 'Ward'],
                                'pu' => ['icon' => 'fa-flag-checkered', 'label' => 'Polling Unit'],
                                'results_summary' => ['icon' => 'fa-file-alt', 'label' => 'Result Summary'],
                                'incident' => ['icon' => 'fa-exclamation-triangle', 'label' => 'Incident'],
                                'personnel' => ['icon' => 'fa-user-chart', 'label' => 'Personnel']
                            ];
                            foreach ($report_types as $key => $type): 
                            ?>
                                <label class="report-type-option <?php echo (isset($report_data['report_type']) && $report_data['report_type'] == $key) ? 'selected' : ''; ?>" onclick="selectReportType('<?php echo $key; ?>')">
                                    <input type="radio" name="report_type" value="<?php echo $key; ?>" <?php echo (isset($report_data['report_type']) && $report_data['report_type'] == $key) ? 'checked' : ''; ?> required>
                                    <span class="icon"><i class="fas <?php echo $type['icon']; ?>"></i></span>
                                    <span class="label"><?php echo $type['label']; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Report Name <span class="required">*</span></label>
                        <input type="text" name="report_name" placeholder="e.g., 2027 Election Progress Report" value="<?php echo htmlspecialchars($report_data['report_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Format <span class="required">*</span></label>
                        <select name="format" required>
                            <option value="pdf" <?php echo (isset($report_data['format']) && $report_data['format'] == 'pdf') ? 'selected' : ''; ?>>PDF</option>
                            <option value="excel" <?php echo (isset($report_data['format']) && $report_data['format'] == 'excel') ? 'selected' : ''; ?>>Excel</option>
                            <option value="csv" <?php echo (isset($report_data['format']) && $report_data['format'] == 'csv') ? 'selected' : ''; ?>>CSV</option>
                        </select>
                    </div>
                    
                    <!-- Filters -->
                    <div class="form-section-title">
                        <i class="fas fa-filter"></i> Filters
                    </div>
                    
                    <div class="form-group">
                        <label>Election <span class="required">*</span></label>
                        <select name="election_id" required>
                            <option value="">Select Election</option>
                            <?php foreach ($elections as $election): ?>
                                <option value="<?php echo $election['id']; ?>" <?php echo (isset($report_data['election_id']) && $report_data['election_id'] == $election['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($election['name']); ?> (<?php echo htmlspecialchars($election['type']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>LGA (Optional)</label>
                        <select name="lga_id" id="lgaSelect" onchange="loadWardsForReport()">
                            <option value="">All LGAs</option>
                            <?php foreach ($lgas as $lga): ?>
                                <option value="<?php echo $lga['id']; ?>" <?php echo (isset($report_data['lga_id']) && $report_data['lga_id'] == $lga['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lga['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" id="wardField">
                        <label>Ward (Optional)</label>
                        <select name="ward_id" id="wardSelect">
                            <option value="">All Wards</option>
                            <?php 
                            $selected_ward = isset($report_data['ward_id']) ? $report_data['ward_id'] : 0;
                            if (isset($report_data['lga_id']) && $report_data['lga_id'] > 0):
                                $wards = [];
                                try {
                                    $stmt = $db->prepare("SELECT id, name FROM wards WHERE lga_id = ? AND is_active = 1 ORDER BY name ASC");
                                    $stmt->execute([$report_data['lga_id']]);
                                    $wards = $stmt->fetchAll();
                                } catch (Exception $e) {}
                                foreach ($wards as $ward): 
                            ?>
                                <option value="<?php echo $ward['id']; ?>" <?php echo ($selected_ward == $ward['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ward['name']); ?>
                                </option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                    
                    <!-- Scheduling -->
                    <div class="form-section-title">
                        <i class="fas fa-clock"></i> Scheduling (Optional)
                    </div>
                    
                    <div class="form-group full-width">
                        <div class="switch-container">
                            <label class="switch">
                                <input type="checkbox" name="schedule" id="scheduleToggle" <?php echo (isset($report_data['schedule']) && $report_data['schedule']) ? 'checked' : ''; ?> onchange="toggleSchedule()">
                                <span class="slider"></span>
                            </label>
                            <span class="switch-label">Schedule this report to run automatically</span>
                        </div>
                    </div>
                    
                    <div class="form-group <?php echo (!isset($report_data['schedule']) || !$report_data['schedule']) ? 'hidden' : ''; ?>" id="scheduleOptions">
                        <label>Frequency</label>
                        <select name="schedule_frequency">
                            <option value="daily" <?php echo (isset($report_data['schedule_frequency']) && $report_data['schedule_frequency'] == 'daily') ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo (isset($report_data['schedule_frequency']) && $report_data['schedule_frequency'] == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo (isset($report_data['schedule_frequency']) && $report_data['schedule_frequency'] == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-file-alt"></i> Generate Report
                    </button>
                    <a href="reports.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
// ============================================================
// SELECT REPORT TYPE
// ============================================================
function selectReportType(type) {
    document.querySelectorAll('.report-type-option').forEach(function(el) {
        el.classList.remove('selected');
    });
    document.querySelector('.report-type-option input[value="' + type + '"]').closest('.report-type-option').classList.add('selected');
}

// ============================================================
// TOGGLE SCHEDULE
// ============================================================
function toggleSchedule() {
    var checked = document.getElementById('scheduleToggle').checked;
    var options = document.getElementById('scheduleOptions');
    if (checked) {
        options.classList.remove('hidden');
    } else {
        options.classList.add('hidden');
    }
}

// ============================================================
// LOAD WARDS FOR REPORT
// ============================================================
function loadWardsForReport() {
    var lgaId = document.getElementById('lgaSelect').value;
    var wardSelect = document.getElementById('wardSelect');
    
    if (!lgaId) {
        wardSelect.innerHTML = '<option value="">All Wards</option>';
        return;
    }
    
    wardSelect.innerHTML = '<option value="">Loading...</option>';
    
    fetch('ajax/get_wards.php?lga_id=' + lgaId)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            wardSelect.innerHTML = '<option value="">All Wards</option>';
            if (data && data.length > 0) {
                data.forEach(function(ward) {
                    var option = document.createElement('option');
                    option.value = ward.id;
                    option.textContent = ward.name;
                    wardSelect.appendChild(option);
                });
            }
        })
        .catch(function() {
            wardSelect.innerHTML = '<option value="">All Wards</option>';
        });
}

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

window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});
</script>
</body>
</html>