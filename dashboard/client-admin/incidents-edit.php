<?php
// ============================================================
// INCIDENT EDIT - CLIENT ADMIN (PROFESSIONAL UI)
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
try {
    $stmt = $db->prepare("
        SELECT i.*, 
               r.full_name as reporter_name,
               a.full_name as assigned_to_name,
               e.name as election_name,
               s.name as state_name,
               l.name as lga_name,
               w.name as ward_name,
               pu.name as pu_name
        FROM incidents i
        LEFT JOIN users r ON i.reporter_id = r.id
        LEFT JOIN users a ON i.assigned_to = a.id
        LEFT JOIN elections e ON i.election_id = e.id
        LEFT JOIN states s ON i.state_id = s.id
        LEFT JOIN lgas l ON i.lga_id = l.id
        LEFT JOIN wards w ON i.ward_id = w.id
        LEFT JOIN polling_units pu ON i.pu_id = pu.id
        WHERE i.id = ? AND i.tenant_id = ?
    ");
    $stmt->execute([$incident_id, $tenant_id]);
    $incident = $stmt->fetch();
    
    if (!$incident) {
        header('Location: incidents.php');
        exit();
    }
} catch (Exception $e) {
    header('Location: incidents.php');
    exit();
}

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
// FETCH INVESTIGATORS FOR DROPDOWN
// ============================================================
$investigators = [];
try {
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, r.name as role_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.tenant_id = ? AND u.status = 'active' 
        AND r.level IN ('state', 'lga', 'ward', 'pu_agent')
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$tenant_id]);
    $investigators = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// DECODE JSON FIELDS
// ============================================================
$photo_urls = json_decode($incident['photo_urls_json'] ?? '[]', true);

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_incident':
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $incident_type = trim($_POST['incident_type'] ?? '');
                $severity = trim($_POST['severity'] ?? '');
                $status = trim($_POST['status'] ?? '');
                $election_id = (int)($_POST['election_id'] ?? 0);
                $state_id = (int)($_POST['state_id'] ?? 0);
                $lga_id = (int)($_POST['lga_id'] ?? 0);
                $ward_id = (int)($_POST['ward_id'] ?? 0);
                $pu_id = (int)($_POST['pu_id'] ?? 0);
                $assigned_to = (int)($_POST['assigned_to'] ?? 0);
                $gps_lat = !empty($_POST['gps_lat']) ? (float)$_POST['gps_lat'] : null;
                $gps_lng = !empty($_POST['gps_lng']) ? (float)$_POST['gps_lng'] : null;
                $gps_accuracy = !empty($_POST['gps_accuracy']) ? (float)$_POST['gps_accuracy'] : null;
                
                if (empty($title) || empty($description) || empty($incident_type) || empty($severity)) {
                    throw new Exception('Title, description, type, and severity are required.');
                }
                
                $stmt = $db->prepare("
                    UPDATE incidents SET 
                        title = ?, description = ?, incident_type = ?,
                        severity = ?, status = ?, election_id = ?,
                        state_id = ?, lga_id = ?, ward_id = ?, pu_id = ?,
                        assigned_to = ?, gps_lat = ?, gps_lng = ?, gps_accuracy = ?,
                        updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([
                    $title, $description, $incident_type,
                    $severity, $status, $election_id,
                    $state_id, $lga_id, $ward_id, $pu_id,
                    $assigned_to, $gps_lat, $gps_lng, $gps_accuracy,
                    $incident_id, $tenant_id
                ]);
                
                logActivity($user_id, 'incident_updated', "Updated incident ID: $incident_id");
                $action_result = ['success' => true, 'message' => 'Incident updated successfully.'];
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
       INCIDENT EDIT - PROFESSIONAL UI STYLES
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
        background: #EFF6FF;
        color: var(--primary);
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
    
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        transition: var(--transition);
    }
    .badge-status .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .badge-status.reported { background: #FEF2F2; color: #991B1B; }
    .badge-status.reported .dot { background: #EF4444; }
    .badge-status.acknowledged { background: #FFFBEB; color: #92400E; }
    .badge-status.acknowledged .dot { background: #F59E0B; }
    .badge-status.investigating { background: #EFF6FF; color: #1E40AF; }
    .badge-status.investigating .dot { background: #3B82F6; }
    .badge-status.escalated { background: #F5F3FF; color: #5B21B6; }
    .badge-status.escalated .dot { background: #8B5CF6; }
    .badge-status.resolved { background: #ECFDF5; color: #065F46; }
    .badge-status.resolved .dot { background: #10B981; }
    .badge-status.closed { background: var(--gray-100); color: var(--gray-500); }
    .badge-status.closed .dot { background: var(--gray-400); }
    .badge-status.false_alarm { background: var(--gray-100); color: var(--gray-500); }
    .badge-status.false_alarm .dot { background: var(--gray-400); }
    
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
    .form-actions .btn-primary {
        background: var(--primary);
        color: white;
    }
    .form-actions .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
    }
    .form-actions .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .form-actions .btn-secondary:hover {
        background: var(--gray-200);
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
    
    .current-attachments {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 4px;
    }
    .current-attachment {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 6px;
        background: var(--gray-50);
        border: 1px solid var(--gray-200);
        font-size: 0.75rem;
        color: var(--gray-600);
    }
    .current-attachment i {
        font-size: 0.8rem;
    }
    .current-attachment .remove {
        color: var(--danger);
        cursor: pointer;
        font-size: 0.7rem;
        margin-left: 4px;
    }
    .current-attachment .remove:hover {
        color: #DC2626;
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
    }
    @media (max-width: 480px) {
        .form-container {
            padding: 12px;
        }
        .form-container .form-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Toast Messages -->
        <?php if (!empty($action_result['message'])): ?>
        <div class="toast <?php echo $action_result['success'] ? 'success' : 'error'; ?>" style="position:static;animation:none;">
            <i class="fas <?php echo $action_result['success'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($action_result['message']); ?>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-edit" style="color:var(--primary);margin-right:8px;"></i> Edit Incident
                    <small>Update incident details and status</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="incidents-view.php?id=<?php echo $incident_id; ?>" class="btn-outline">
                    <i class="fas fa-eye"></i> View
                </a>
                <a href="incidents.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <div class="form-header">
                <div class="icon">
                    <i class="fas fa-exclamation-triangle" style="color:var(--danger);"></i>
                </div>
                <div>
                    <h3>Edit Incident #<?php echo $incident_id; ?></h3>
                    <p>Update the incident details. Fields marked with <span style="color:var(--danger);">*</span> are required.</p>
                </div>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="action" value="update_incident">
                
                <div class="form-grid">
                    <!-- Title -->
                    <div class="form-group full-width">
                        <label>Incident Title <span class="required">*</span></label>
                        <input type="text" name="title" placeholder="e.g., Ballot Box Snatching at PU 001" required
                               value="<?php echo htmlspecialchars($incident['title']); ?>">
                    </div>

                    <!-- Description -->
                    <div class="form-group full-width">
                        <label>Description <span class="required">*</span></label>
                        <textarea name="description" placeholder="Detailed description of the incident..." rows="4" required><?php echo htmlspecialchars($incident['description']); ?></textarea>
                    </div>

                    <!-- Incident Type -->
                    <div class="form-group">
                        <label>Incident Type <span class="required">*</span></label>
                        <select name="incident_type" required>
                            <option value="violence" <?php echo $incident['incident_type'] == 'violence' ? 'selected' : ''; ?>>Violence</option>
                            <option value="vote_buying" <?php echo $incident['incident_type'] == 'vote_buying' ? 'selected' : ''; ?>>Vote Buying</option>
                            <option value="ballot_stuffing" <?php echo $incident['incident_type'] == 'ballot_stuffing' ? 'selected' : ''; ?>>Ballot Box Snatching</option>
                            <option value="intimidation" <?php echo $incident['incident_type'] == 'intimidation' ? 'selected' : ''; ?>>Intimidation</option>
                            <option value="material_shortage" <?php echo $incident['incident_type'] == 'material_shortage' ? 'selected' : ''; ?>>Late Arrival of Materials</option>
                            <option value="security" <?php echo $incident['incident_type'] == 'security' ? 'selected' : ''; ?>>Security Issues</option>
                            <option value="technical_issue" <?php echo $incident['incident_type'] == 'technical_issue' ? 'selected' : ''; ?>>Technical Issues</option>
                            <option value="panic_button" <?php echo $incident['incident_type'] == 'panic_button' ? 'selected' : ''; ?>>Panic Button</option>
                            <option value="other" <?php echo $incident['incident_type'] == 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <!-- Severity -->
                    <div class="form-group">
                        <label>Severity <span class="required">*</span></label>
                        <select name="severity" required>
                            <option value="low" <?php echo $incident['severity'] == 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $incident['severity'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $incident['severity'] == 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="critical" <?php echo $incident['severity'] == 'critical' ? 'selected' : ''; ?>>Critical</option>
                        </select>
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label>Status <span class="required">*</span></label>
                        <select name="status" required>
                            <option value="reported" <?php echo $incident['status'] == 'reported' ? 'selected' : ''; ?>>Reported</option>
                            <option value="acknowledged" <?php echo $incident['status'] == 'acknowledged' ? 'selected' : ''; ?>>Acknowledged</option>
                            <option value="investigating" <?php echo $incident['status'] == 'investigating' ? 'selected' : ''; ?>>Investigating</option>
                            <option value="escalated" <?php echo $incident['status'] == 'escalated' ? 'selected' : ''; ?>>Escalated</option>
                            <option value="resolved" <?php echo $incident['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            <option value="closed" <?php echo $incident['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                            <option value="false_alarm" <?php echo $incident['status'] == 'false_alarm' ? 'selected' : ''; ?>>False Alarm</option>
                        </select>
                    </div>

                    <!-- Election -->
                    <div class="form-group">
                        <label>Election</label>
                        <select name="election_id">
                            <option value="0">Select Election</option>
                            <?php foreach ($elections as $election): ?>
                                <option value="<?php echo $election['id']; ?>" <?php echo $incident['election_id'] == $election['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($election['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Assigned To -->
                    <div class="form-group">
                        <label>Assign Investigator</label>
                        <select name="assigned_to">
                            <option value="0">Unassigned</option>
                            <?php foreach ($investigators as $investigator): ?>
                                <option value="<?php echo $investigator['id']; ?>" <?php echo $incident['assigned_to'] == $investigator['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($investigator['first_name'] . ' ' . $investigator['last_name']); ?>
                                    (<?php echo htmlspecialchars($investigator['role_name'] ?? 'N/A'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Location -->
                    <div class="form-group full-width">
                        <label>Location Details</label>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;">
                            <select name="state_id" id="stateSelect">
                                <option value="0">State</option>
                                <?php foreach ($states as $state): ?>
                                    <option value="<?php echo $state['id']; ?>" <?php echo $incident['state_id'] == $state['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($state['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="lga_id" id="lgaSelect">
                                <option value="0">LGA</option>
                                <?php foreach ($lgas as $lga): ?>
                                    <option value="<?php echo $lga['id']; ?>" <?php echo $incident['lga_id'] == $lga['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lga['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="ward_id" id="wardSelect">
                                <option value="0">Ward</option>
                                <?php foreach ($wards as $ward): ?>
                                    <option value="<?php echo $ward['id']; ?>" <?php echo $incident['ward_id'] == $ward['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ward['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="pu_id" id="puSelect">
                                <option value="0">Polling Unit</option>
                                <?php foreach ($polling_units as $pu): ?>
                                    <option value="<?php echo $pu['id']; ?>" <?php echo $incident['pu_id'] == $pu['id'] ? 'selected' : ''; ?>>
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
                            <input type="text" name="gps_lat" placeholder="Latitude" 
                                   value="<?php echo $incident['gps_lat'] ?? ''; ?>">
                            <input type="text" name="gps_lng" placeholder="Longitude"
                                   value="<?php echo $incident['gps_lng'] ?? ''; ?>">
                            <input type="text" name="gps_accuracy" placeholder="Accuracy (meters)"
                                   value="<?php echo $incident['gps_accuracy'] ?? ''; ?>">
                        </div>
                        <div class="help-text">e.g., 6.5244 for latitude, 3.3792 for longitude</div>
                    </div>

                    <!-- Attachments Info -->
                    <?php if (!empty($photo_urls) || !empty($incident['video_url']) || !empty($incident['audio_url'])): ?>
                    <div class="form-group full-width">
                        <label>Current Attachments</label>
                        <div class="current-attachments">
                            <?php foreach ($photo_urls as $photo): ?>
                                <span class="current-attachment">
                                    <i class="fas fa-image"></i> Photo
                                    <span class="remove" onclick="alert('Remove attachment')"><i class="fas fa-times"></i></span>
                                </span>
                            <?php endforeach; ?>
                            <?php if (!empty($incident['video_url'])): ?>
                                <span class="current-attachment">
                                    <i class="fas fa-video"></i> Video
                                    <span class="remove" onclick="alert('Remove attachment')"><i class="fas fa-times"></i></span>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($incident['audio_url'])): ?>
                                <span class="current-attachment">
                                    <i class="fas fa-microphone"></i> Audio
                                    <span class="remove" onclick="alert('Remove attachment')"><i class="fas fa-times"></i></span>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="help-text">To add new attachments, use the edit page in the full version</div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <a href="incidents-view.php?id=<?php echo $incident_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Incident
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