<?php
// ============================================================
// INCIDENT REPORT - CLIENT ADMIN (PROFESSIONAL UI)
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
// FETCH ELECTIONS FOR DROPDOWN
// ============================================================
$elections = [];
try {
    $stmt = $db->prepare("SELECT id, name, type, status, election_date FROM elections WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY election_date DESC");
    $stmt->execute([$tenant_id]);
    $elections = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH STATES FOR DROPDOWN
// ============================================================
$states = [];
try {
    $stmt = $db->query("SELECT id, name, code FROM states WHERE is_active = 1 ORDER BY name");
    $states = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH LGAS FOR DROPDOWN
// ============================================================
$lgas = [];
try {
    $stmt = $db->query("SELECT id, name, code FROM lgas WHERE is_active = 1 ORDER BY name");
    $lgas = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH WARDS FOR DROPDOWN
// ============================================================
$wards = [];
try {
    $stmt = $db->query("SELECT id, name, code FROM wards WHERE is_active = 1 ORDER BY name");
    $wards = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH POLLING UNITS FOR DROPDOWN
// ============================================================
$polling_units = [];
try {
    $stmt = $db->query("SELECT id, code, name FROM polling_units WHERE is_active = 1 ORDER BY name LIMIT 500");
    $polling_units = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'report_incident':
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $incident_type = trim($_POST['incident_type'] ?? '');
                $severity = trim($_POST['severity'] ?? '');
                $election_id = (int)($_POST['election_id'] ?? 0);
                $state_id = (int)($_POST['state_id'] ?? 0);
                $lga_id = (int)($_POST['lga_id'] ?? 0);
                $ward_id = (int)($_POST['ward_id'] ?? 0);
                $pu_id = (int)($_POST['pu_id'] ?? 0);
                $gps_lat = !empty($_POST['gps_lat']) ? (float)$_POST['gps_lat'] : null;
                $gps_lng = !empty($_POST['gps_lng']) ? (float)$_POST['gps_lng'] : null;
                $gps_accuracy = !empty($_POST['gps_accuracy']) ? (float)$_POST['gps_accuracy'] : null;
                $is_panic = isset($_POST['is_panic']) ? 1 : 0;
                
                if (empty($title) || empty($description) || empty($incident_type) || empty($severity)) {
                    throw new Exception('Title, description, type, and severity are required.');
                }
                
                // Handle file uploads
                $photo_urls = [];
                $video_url = '';
                $audio_url = '';
                
                // In production, handle file uploads here
                // For demo, we'll just simulate
                if (!empty($_FILES['photos']['name'][0])) {
                    foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
                        if (!empty($tmp_name)) {
                            $photo_urls[] = '/uploads/incidents/' . uniqid() . '_' . basename($_FILES['photos']['name'][$key]);
                        }
                    }
                }
                
                $photo_urls_json = json_encode($photo_urls);
                
                $stmt = $db->prepare("
                    INSERT INTO incidents (
                        tenant_id, election_id, reporter_id, pu_id, ward_id, lga_id, state_id,
                        incident_type, severity, is_panic, title, description,
                        gps_lat, gps_lng, gps_accuracy, photo_urls_json, video_url, audio_url,
                        status, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'reported', NOW(), NOW())
                ");
                $stmt->execute([
                    $tenant_id, $election_id, $user_id, $pu_id, $ward_id, $lga_id, $state_id,
                    $incident_type, $severity, $is_panic, $title, $description,
                    $gps_lat, $gps_lng, $gps_accuracy, $photo_urls_json, $video_url, $audio_url
                ]);
                
                $incident_id = $db->lastInsertId();
                logActivity($user_id, 'incident_reported', "Reported incident ID: $incident_id");
                
                $action_result = ['success' => true, 'message' => 'Incident reported successfully!', 'id' => $incident_id];
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       INCIDENT REPORT - PROFESSIONAL UI STYLES
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
    .btn-secondary {
        padding: 10px 20px;
        background: var(--gray-100);
        color: var(--gray-600);
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
    .btn-secondary:hover {
        background: var(--gray-200);
    }
    .btn-success {
        padding: 10px 20px;
        background: var(--secondary);
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
    .btn-success:hover {
        background: #059669;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(16, 185, 129, 0.3);
    }
    
    .form-container {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 28px 32px;
        box-shadow: var(--shadow);
        max-width: 900px;
        margin: 0 auto;
    }
    .form-container:hover {
        box-shadow: var(--shadow-hover);
    }
    .form-container .form-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 2px solid var(--gray-100);
    }
    .form-container .form-header .icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: #FEF2F2;
        color: var(--danger);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
    }
    .form-container .form-header h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--gray-800);
    }
    .form-container .form-header p {
        font-size: 0.85rem;
        color: var(--gray-500);
        margin-top: 2px;
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
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
    .form-group select,
    .form-group textarea {
        padding: 8px 12px;
        border: 1.5px solid var(--gray-200);
        border-radius: 8px;
        font-family: 'Inter', sans-serif;
        font-size: 0.82rem;
        transition: var(--transition);
        background: var(--gray-50);
        color: var(--gray-700);
        width: 100%;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.08);
    }
    .form-group textarea {
        resize: vertical;
        min-height: 100px;
    }
    
    .file-upload-area {
        border: 2px dashed var(--gray-200);
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        background: var(--gray-50);
        position: relative;
    }
    .file-upload-area:hover {
        border-color: var(--primary);
        background: #EFF6FF;
    }
    .file-upload-area.dragover {
        border-color: var(--primary);
        background: #EFF6FF;
        transform: scale(1.01);
    }
    .file-upload-area i {
        font-size: 2rem;
        color: var(--gray-400);
        display: block;
        margin-bottom: 8px;
        transition: var(--transition);
    }
    .file-upload-area:hover i {
        color: var(--primary);
    }
    .file-upload-area p {
        font-size: 0.85rem;
        color: var(--gray-500);
        margin-bottom: 2px;
    }
    .file-upload-area .file-types {
        font-size: 0.7rem;
        color: var(--gray-400);
    }
    .file-upload-area input[type="file"] {
        display: none;
    }
    
    .file-preview-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
        gap: 8px;
        margin-top: 10px;
    }
    .file-preview-item {
        position: relative;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid var(--gray-200);
        aspect-ratio: 1;
        background: var(--gray-50);
    }
    .file-preview-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .file-preview-item .file-type {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        font-size: 1.5rem;
        color: var(--gray-400);
    }
    .file-preview-item .file-type .name {
        font-size: 0.5rem;
        text-align: center;
        word-break: break-all;
        margin-top: 4px;
        color: var(--gray-500);
    }
    .file-preview-item .remove-btn {
        position: absolute;
        top: 2px;
        right: 2px;
        background: rgba(239, 68, 68, 0.8);
        color: white;
        border: none;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 0.6rem;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .file-preview-item .remove-btn:hover {
        background: #DC2626;
        transform: scale(1.1);
    }
    
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 0;
    }
    .checkbox-group input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: var(--danger);
        cursor: pointer;
        flex-shrink: 0;
    }
    .checkbox-group label {
        font-weight: 500;
        cursor: pointer;
        font-size: 0.85rem;
        color: var(--gray-700);
    }
    .checkbox-group .help-text {
        font-size: 0.7rem;
        color: var(--gray-400);
        margin-left: 4px;
    }
    
    .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 2px solid var(--gray-100);
    }
    .form-actions .btn {
        padding: 10px 24px;
        border-radius: 8px;
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
    
    .toast {
        padding: 12px 18px;
        border-radius: 8px;
        color: white;
        font-size: 0.82rem;
        font-weight: 500;
        box-shadow: var(--shadow-hover);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        max-width: 100%;
    }
    .toast.success { background: var(--secondary); }
    .toast.error { background: var(--danger); }
    
    .success-banner {
        background: #ECFDF5;
        border: 1px solid #A7F3D0;
        border-radius: 10px;
        padding: 20px 24px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }
    .success-banner .icon {
        font-size: 2rem;
        color: #10B981;
    }
    .success-banner .info h4 {
        color: #065F46;
        font-weight: 700;
        font-size: 1rem;
        margin-bottom: 2px;
    }
    .success-banner .info p {
        color: #065F46;
        font-size: 0.85rem;
    }
    .success-banner .actions {
        margin-left: auto;
        display: flex;
        gap: 10px;
    }
    
    @media (max-width: 768px) {
        .form-container {
            padding: 16px;
        }
        .form-grid {
            grid-template-columns: 1fr;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .success-banner {
            flex-direction: column;
            text-align: center;
        }
        .success-banner .actions {
            margin-left: 0;
            width: 100%;
        }
        .success-banner .actions .btn {
            flex: 1;
            justify-content: center;
        }
        .file-preview-grid {
            grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
        }
    }
    @media (max-width: 480px) {
        .form-container {
            padding: 12px;
        }
        .form-container .form-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .file-preview-grid {
            grid-template-columns: repeat(auto-fill, minmax(50px, 1fr));
        }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Toast Messages -->
        <?php if (!empty($action_result['message'])): ?>
            <?php if ($action_result['success']): ?>
                <div class="success-banner">
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                    <div class="info">
                        <h4><?php echo htmlspecialchars($action_result['message']); ?></h4>
                        <p>Incident has been reported and is now being processed.</p>
                    </div>
                    <div class="actions">
                        <a href="incidents-view.php?id=<?php echo $action_result['id'] ?? 0; ?>" class="btn btn-primary" style="padding:8px 16px;font-size:0.8rem;text-decoration:none;">
                            <i class="fas fa-eye"></i> View Incident
                        </a>
                        <a href="incidents-report.php" class="btn btn-outline" style="padding:8px 16px;font-size:0.8rem;text-decoration:none;">
                            <i class="fas fa-plus"></i> Report Another
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="toast error" style="position:static;animation:none;">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($action_result['message']); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-plus-circle" style="color:var(--danger);margin-right:8px;"></i> Report Incident
                    <small>Report an election-related incident from the field</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="incidents.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Incidents
                </a>
            </div>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <div class="form-header">
                <div class="icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <h3>Incident Report Form</h3>
                    <p>Fill in the details below to report an incident. Fields marked with <span style="color:var(--danger);">*</span> are required.</p>
                </div>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="report_incident">
                
                <div class="form-grid">
                    <!-- Title -->
                    <div class="form-group full-width">
                        <label>Incident Title <span class="required">*</span></label>
                        <input type="text" name="title" placeholder="e.g., Ballot Box Snatching at PU 001" required>
                    </div>

                    <!-- Description -->
                    <div class="form-group full-width">
                        <label>Description <span class="required">*</span></label>
                        <textarea name="description" placeholder="Provide a detailed description of the incident..." rows="4" required></textarea>
                        <div class="help-text">Include as much detail as possible about what happened</div>
                    </div>

                    <!-- Incident Type -->
                    <div class="form-group">
                        <label>Incident Type <span class="required">*</span></label>
                        <select name="incident_type" required>
                            <option value="">Select Type</option>
                            <option value="violence">Violence</option>
                            <option value="vote_buying">Vote Buying</option>
                            <option value="ballot_stuffing">Ballot Box Snatching</option>
                            <option value="intimidation">Intimidation</option>
                            <option value="material_shortage">Late Arrival of Materials</option>
                            <option value="security">Security Issues</option>
                            <option value="technical_issue">Technical Issues</option>
                            <option value="panic_button">Panic Button</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <!-- Severity -->
                    <div class="form-group">
                        <label>Severity <span class="required">*</span></label>
                        <select name="severity" required>
                            <option value="">Select Severity</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>

                    <!-- Election -->
                    <div class="form-group">
                        <label>Election</label>
                        <select name="election_id">
                            <option value="0">Select Election</option>
                            <?php foreach ($elections as $election): ?>
                                <option value="<?php echo $election['id']; ?>">
                                    <?php echo htmlspecialchars($election['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">Optional: Link this incident to an election</div>
                    </div>

                    <!-- Panic Button -->
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_panic" id="isPanic" value="1">
                            <label for="isPanic">
                                <i class="fas fa-exclamation-triangle" style="color:var(--danger);"></i>
                                Panic Alert
                                <span class="help-text">Mark as high priority emergency</span>
                            </label>
                        </div>
                    </div>

                    <!-- Location -->
                    <div class="form-group full-width">
                        <label>Location Details</label>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;">
                            <select name="state_id" id="stateSelect">
                                <option value="0">State</option>
                                <?php foreach ($states as $state): ?>
                                    <option value="<?php echo $state['id']; ?>">
                                        <?php echo htmlspecialchars($state['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="lga_id" id="lgaSelect">
                                <option value="0">LGA</option>
                                <?php foreach ($lgas as $lga): ?>
                                    <option value="<?php echo $lga['id']; ?>">
                                        <?php echo htmlspecialchars($lga['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="ward_id" id="wardSelect">
                                <option value="0">Ward</option>
                                <?php foreach ($wards as $ward): ?>
                                    <option value="<?php echo $ward['id']; ?>">
                                        <?php echo htmlspecialchars($ward['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="pu_id" id="puSelect">
                                <option value="0">Polling Unit</option>
                                <?php foreach ($polling_units as $pu): ?>
                                    <option value="<?php echo $pu['id']; ?>">
                                        <?php echo htmlspecialchars($pu['code']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="help-text">Select the location hierarchy for this incident</div>
                    </div>

                    <!-- GPS Coordinates -->
                    <div class="form-group full-width">
                        <label>GPS Coordinates</label>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
                            <input type="text" name="gps_lat" placeholder="Latitude (e.g., 6.5244)">
                            <input type="text" name="gps_lng" placeholder="Longitude (e.g., 3.3792)">
                            <input type="text" name="gps_accuracy" placeholder="Accuracy (meters)">
                        </div>
                        <div class="help-text">Optional: Add GPS coordinates for precise location</div>
                    </div>

                    <!-- File Uploads -->
                    <div class="form-group full-width">
                        <label>Attachments</label>
                        <div class="file-upload-area" onclick="document.getElementById('fileInput').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to upload photos, videos, or audio</p>
                            <div class="file-types">Supported: JPG, PNG, MP4, MP3, PDF (Max 10MB each)</div>
                            <input type="file" name="photos[]" id="fileInput" accept=".jpg,.jpeg,.png,.gif,.mp4,.mp3,.pdf,.doc,.docx" multiple>
                        </div>
                        <div id="filePreviewContainer" class="file-preview-grid" style="display:none;"></div>
                        <div class="help-text" id="fileCount">No files selected</div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="incidents.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-paper-plane"></i> Report Incident
                    </button>
                </div>
            </form>
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
// FILE UPLOAD PREVIEW
// ============================================================
document.getElementById('fileInput').addEventListener('change', function(e) {
    var container = document.getElementById('filePreviewContainer');
    var countLabel = document.getElementById('fileCount');
    var files = this.files;
    
    if (files.length === 0) {
        container.style.display = 'none';
        countLabel.textContent = 'No files selected';
        return;
    }
    
    container.style.display = 'grid';
    container.innerHTML = '';
    countLabel.textContent = files.length + ' file(s) selected';
    
    for (var i = 0; i < files.length; i++) {
        var file = files[i];
        var item = document.createElement('div');
        item.className = 'file-preview-item';
        
        var removeBtn = document.createElement('button');
        removeBtn.className = 'remove-btn';
        removeBtn.innerHTML = '<i class="fas fa-times"></i>';
        removeBtn.onclick = function(e) {
            e.stopPropagation();
            this.parentElement.remove();
            // Update file list
            var dt = new DataTransfer();
            var input = document.getElementById('fileInput');
            var remaining = [];
            for (var j = 0; j < input.files.length; j++) {
                if (j !== i) {
                    dt.items.add(input.files[j]);
                }
            }
            input.files = dt.files;
            document.getElementById('fileInput').dispatchEvent(new Event('change'));
        };
        
        if (file.type.startsWith('image/')) {
            var img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = file.name;
            item.appendChild(img);
        } else {
            var iconDiv = document.createElement('div');
            iconDiv.className = 'file-type';
            var icon = document.createElement('i');
            if (file.type.startsWith('video/')) {
                icon.className = 'fas fa-video';
            } else if (file.type.startsWith('audio/')) {
                icon.className = 'fas fa-microphone';
            } else if (file.type.includes('pdf')) {
                icon.className = 'fas fa-file-pdf';
            } else {
                icon.className = 'fas fa-file';
            }
            var nameSpan = document.createElement('div');
            nameSpan.className = 'name';
            nameSpan.textContent = file.name.substring(0, 10) + (file.name.length > 10 ? '...' : '');
            iconDiv.appendChild(icon);
            iconDiv.appendChild(nameSpan);
            item.appendChild(iconDiv);
        }
        
        item.appendChild(removeBtn);
        container.appendChild(item);
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