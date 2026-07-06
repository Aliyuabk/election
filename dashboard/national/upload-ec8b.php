<?php
// ============================================================
// NATIONAL COORDINATOR - UPLOAD EC8B FORM
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

// Only national coordinator can access
if (SessionManager::get('role_level') !== 'national') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');

// Get parameters
$ward_id = isset($_GET['ward']) ? intval($_GET['ward']) : 0;
$lga_id = isset($_GET['lga']) ? intval($_GET['lga']) : 0;
$state_id = isset($_GET['state']) ? intval($_GET['state']) : 0;

$db = getDB();

// ============================================================
// FETCH LOCATION DATA
// ============================================================
$location_name = '';
$back_url = 'monitor-states.php';
$ward_name = '';
$lga_name = '';
$state_name = '';

if ($ward_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT w.name as ward_name, l.name as lga_name, s.name as state_name
            FROM wards w
            JOIN lgas l ON w.lga_id = l.id
            JOIN states s ON l.state_id = s.id
            WHERE w.id = ?
        ");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['ward_name'];
            $lga_name = $result['lga_name'];
            $state_name = $result['state_name'];
            $location_name = "$ward_name ($lga_name, $state_name)";
            $back_url = "ward-dashboard.php?id=$ward_id";
        }
    } catch (Exception $e) {
        error_log("Location fetch error: " . $e->getMessage());
    }
} elseif ($lga_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT l.name as lga_name, s.name as state_name
            FROM lgas l
            JOIN states s ON l.state_id = s.id
            WHERE l.id = ?
        ");
        $stmt->execute([$lga_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $lga_name = $result['lga_name'];
            $state_name = $result['state_name'];
            $location_name = "$lga_name ($state_name)";
            $back_url = "lga-dashboard.php?id=$lga_id";
        }
    } catch (Exception $e) {
        error_log("Location fetch error: " . $e->getMessage());
    }
} elseif ($state_id > 0) {
    try {
        $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
        $stmt->execute([$state_id]);
        $state_name = $stmt->fetchColumn();
        $location_name = $state_name;
        $back_url = "view-state.php?id=$state_id";
    } catch (Exception $e) {
        error_log("State fetch error: " . $e->getMessage());
    }
}

// ============================================================
// FETCH WARDS FOR SELECTION
// ============================================================
$wards = [];
if ($lga_id > 0) {
    try {
        $stmt = $db->prepare("SELECT id, name FROM wards WHERE lga_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$lga_id]);
        $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $wards = [];
    }
}

// ============================================================
// FETCH LGAS FOR SELECTION
// ============================================================
$lgas = [];
if ($state_id > 0) {
    try {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$state_id]);
        $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $lgas = [];
    }
}

// ============================================================
// FETCH STATES FOR SELECTION
// ============================================================
$states = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM states WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $states = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $states = [];
}

// ============================================================
// PROCESS FORM SUBMISSION
// ============================================================
$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ward_id_post = intval($_POST['ward_id'] ?? 0);
    $lga_id_post = intval($_POST['lga_id'] ?? 0);
    $state_id_post = intval($_POST['state_id'] ?? 0);
    $party_votes_json = $_POST['party_votes'] ?? '{}';
    $valid_votes = intval($_POST['valid_votes'] ?? 0);
    $rejected_votes = intval($_POST['rejected_votes'] ?? 0);
    $total_votes = intval($_POST['total_votes'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');
    $status = $_POST['status'] ?? 'pending';
    
    // Handle file upload
    $form_photo_url = '';
    if (isset($_FILES['form_photo']) && $_FILES['form_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/ec8b/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = pathinfo($_FILES['form_photo']['name'], PATHINFO_EXTENSION);
        $file_name = 'ec8b_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['form_photo']['tmp_name'], $file_path)) {
            $form_photo_url = '/uploads/ec8b/' . $file_name;
        } else {
            $error = 'Failed to upload form photo';
        }
    }
    
    // Validation
    if (empty($error)) {
        if ($ward_id_post <= 0) {
            $error = 'Please select a ward';
        } elseif ($lga_id_post <= 0) {
            $error = 'Please select an LGA';
        } elseif ($state_id_post <= 0) {
            $error = 'Please select a state';
        } elseif (empty($party_votes_json) || $party_votes_json === '{}') {
            $error = 'Please enter party votes';
        } else {
            try {
                // Check if EC8B already exists for this ward
                $stmt = $db->prepare("
                    SELECT id FROM results_ec8b 
                    WHERE tenant_id = ? AND ward_id = ? 
                    AND (status = 'pending' OR status = 'verified')
                ");
                $stmt->execute([$tenant_id, $ward_id_post]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $error = 'EC8B already exists for this ward. Please edit the existing form.';
                } else {
                    // Insert EC8B
                    $stmt = $db->prepare("
                        INSERT INTO results_ec8b (
                            tenant_id, election_id, ward_id, lga_id, state_id,
                            coordinator_id, party_votes_json, valid_votes, rejected_votes,
                            total_votes, form_photo_url, status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    // Get active election for this ward
                    $election_id = null;
                    $stmt_election = $db->prepare("
                        SELECT id FROM elections 
                        WHERE tenant_id = ? AND status = 'active' 
                        AND JSON_CONTAINS(wards_json, JSON_QUOTE(?))
                        LIMIT 1
                    ");
                    $stmt_election->execute([$tenant_id, $ward_id_post]);
                    $election = $stmt_election->fetch(PDO::FETCH_ASSOC);
                    if ($election) {
                        $election_id = $election['id'];
                    }
                    
                    $stmt->execute([
                        $tenant_id,
                        $election_id,
                        $ward_id_post,
                        $lga_id_post,
                        $state_id_post,
                        $user_id,
                        $party_votes_json,
                        $valid_votes,
                        $rejected_votes,
                        $total_votes,
                        $form_photo_url,
                        $status
                    ]);
                    
                    $ec8b_id = $db->lastInsertId();
                    
                    // Log activity
                    $log_stmt = $db->prepare("
                        INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, entity_type, entity_id, created_at)
                        VALUES (?, ?, 'ec8b_uploaded', ?, 'ec8b', ?, NOW())
                    ");
                    $log_stmt->execute([
                        $user_id,
                        $tenant_id,
                        "Uploaded EC8B form for ward: $ward_name",
                        $ec8b_id
                    ]);
                    
                    $success = true;
                    $message = "EC8B form uploaded successfully!";
                    
                    // Redirect
                    if ($ward_id_post > 0) {
                        header("Location: ward-dashboard.php?id=$ward_id_post&ec8b_success=1");
                    } else {
                        header("Location: ec8b-forms.php?success=1");
                    }
                    exit();
                }
            } catch (Exception $e) {
                $error = 'Failed to upload EC8B: ' . $e->getMessage();
                error_log("EC8B Upload Error: " . $e->getMessage());
            }
        }
    }
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Upload EC8B Form';
$page_subtitle = 'Ward Collation Form';
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Breadcrumb -->
        <div class="welcome-section">
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <a href="../national/index.php" style="text-decoration:none;color:var(--gray-500);">Dashboard</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="<?php echo $back_url; ?>" style="text-decoration:none;color:var(--gray-500);">
                    <?php echo htmlspecialchars($location_name ?: 'Back'); ?>
                </a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Upload EC8B</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-upload" style="color:var(--primary);"></i>
                        Upload EC8B Form
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        Ward Collation Form for <?php echo htmlspecialchars($location_name ?: 'Selected Location'); ?>
                    </p>
                </div>
                <a href="<?php echo $back_url; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($message && $success): ?>
            <div style="background:#D1FAE5;color:#065F46;padding:12px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #A7F3D0;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-check-circle" style="font-size:1.2rem;"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background:#FEE2E2;color:#991B1B;padding:12px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #FECACA;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-exclamation-circle" style="font-size:1.2rem;"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- EC8B Form -->
        <form method="POST" action="" enctype="multipart/form-data" style="background:white;border-radius:var(--radius);padding:24px;border:1px solid var(--gray-200);">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <!-- Left Column -->
                <div>
                    <!-- Location Selection -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Location <span style="color:#EF4444;">*</span>
                        </label>
                        
                        <!-- State -->
                        <div style="margin-bottom:8px;">
                            <select name="state_id" class="form-control" id="stateSelect" required
                                    style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;transition:var(--transition);">
                                <option value="">Select State...</option>
                                <?php foreach ($states as $state): ?>
                                    <option value="<?php echo $state['id']; ?>" 
                                        <?php echo ($_POST['state_id'] ?? $state_id) == $state['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($state['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- LGA -->
                        <div style="margin-bottom:8px;">
                            <select name="lga_id" class="form-control" id="lgaSelect" required
                                    style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;transition:var(--transition);">
                                <option value="">Select LGA...</option>
                                <?php foreach ($lgas as $lga): ?>
                                    <option value="<?php echo $lga['id']; ?>" 
                                        <?php echo ($_POST['lga_id'] ?? $lga_id) == $lga['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lga['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Ward -->
                        <div>
                            <select name="ward_id" class="form-control" id="wardSelect" required
                                    style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;transition:var(--transition);">
                                <option value="">Select Ward...</option>
                                <?php foreach ($wards as $ward): ?>
                                    <option value="<?php echo $ward['id']; ?>" 
                                        <?php echo ($_POST['ward_id'] ?? $ward_id) == $ward['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ward['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Party Votes -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Party Votes <span style="color:#EF4444;">*</span>
                        </label>
                        <div id="partyVotesContainer">
                            <?php
                            // Fetch parties for this tenant
                            $parties = [];
                            try {
                                $stmt = $db->prepare("SELECT id, name, acronym FROM political_parties WHERE tenant_id = ? AND is_active = 1 ORDER BY name");
                                $stmt->execute([$tenant_id]);
                                $parties = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (Exception $e) {
                                $parties = [];
                            }
                            
                            if (count($parties) > 0):
                                foreach ($parties as $index => $party):
                            ?>
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                                    <span style="font-size:0.75rem;font-weight:500;min-width:80px;">
                                        <?php echo htmlspecialchars($party['acronym'] ?: $party['name']); ?>
                                    </span>
                                    <input type="number" name="party_votes[<?php echo $party['id']; ?>]" 
                                           class="form-control party-vote-input"
                                           value="<?php echo htmlspecialchars($_POST['party_votes'][$party['id']] ?? 0); ?>"
                                           min="0" 
                                           style="width:100px;padding:6px 10px;border:1px solid var(--gray-200);border-radius:6px;font-family:'Inter',sans-serif;font-size:0.8rem;transition:var(--transition);">
                                    <span style="font-size:0.65rem;color:var(--gray-400);">votes</span>
                                </div>
                            <?php endforeach; 
                            else: ?>
                                <p style="color:var(--gray-400);font-size:0.8rem;">No parties available. Please add parties first.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div>
                    <!-- Form Photo Upload -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            EC8B Form Photo <span style="color:#EF4444;">*</span>
                        </label>
                        <div style="border:2px dashed var(--gray-200);border-radius:10px;padding:20px;text-align:center;transition:var(--transition);" id="dropZone">
                            <i class="fas fa-camera" style="font-size:2rem;color:var(--gray-300);display:block;margin-bottom:8px;"></i>
                            <p style="color:var(--gray-500);font-size:0.85rem;margin:0;">
                                Drag & drop or click to upload
                            </p>
                            <p style="color:var(--gray-400);font-size:0.7rem;margin:4px 0 0;">
                                Supported: JPG, PNG, PDF (Max 5MB)
                            </p>
                            <input type="file" name="form_photo" accept="image/*,.pdf" 
                                   style="position:absolute;opacity:0;width:100%;height:100%;cursor:pointer;" 
                                   id="fileInput" required>
                        </div>
                        <div id="filePreview" style="margin-top:8px;display:none;">
                            <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#F0FDF4;border-radius:8px;border:1px solid #A7F3D0;">
                                <i class="fas fa-file-image" style="color:#10B981;"></i>
                                <span id="fileName" style="font-size:0.8rem;color:#065F46;"></span>
                                <button type="button" onclick="clearFile()" style="margin-left:auto;background:none;border:none;color:#EF4444;cursor:pointer;font-size:0.8rem;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Vote Totals -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Vote Totals
                        </label>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                            <div>
                                <label style="display:block;font-size:0.7rem;color:var(--gray-500);">Valid Votes</label>
                                <input type="number" name="valid_votes" class="form-control"
                                       value="<?php echo htmlspecialchars($_POST['valid_votes'] ?? 0); ?>"
                                       min="0" 
                                       style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                            </div>
                            <div>
                                <label style="display:block;font-size:0.7rem;color:var(--gray-500);">Rejected Votes</label>
                                <input type="number" name="rejected_votes" class="form-control"
                                       value="<?php echo htmlspecialchars($_POST['rejected_votes'] ?? 0); ?>"
                                       min="0" 
                                       style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                            </div>
                        </div>
                        <div style="margin-top:8px;">
                            <label style="display:block;font-size:0.7rem;color:var(--gray-500);">Total Votes</label>
                            <input type="number" name="total_votes" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['total_votes'] ?? 0); ?>"
                                   min="0" 
                                   style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                        </div>
                    </div>
                    
                    <!-- Remarks -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Remarks
                        </label>
                        <textarea name="remarks" class="form-control" rows="2"
                                  placeholder="Additional remarks..."
                                  style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;resize:vertical;transition:var(--transition);"><?php echo htmlspecialchars($_POST['remarks'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Status -->
                    <div>
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Status
                        </label>
                        <select name="status" class="form-control"
                                style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                            <option value="pending" <?php echo ($_POST['status'] ?? 'pending') == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="verified" <?php echo ($_POST['status'] ?? '') == 'verified' ? 'selected' : ''; ?>>Verified</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div style="display:flex;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-200);flex-wrap:wrap;">
                <button type="submit" class="btn-primary" style="padding:10px 32px;background:var(--primary);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-upload"></i> Upload EC8B
                </button>
                <button type="reset" class="btn-secondary" style="padding:10px 32px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-weight:500;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <a href="<?php echo $back_url; ?>" class="btn-secondary" style="padding:10px 32px;background:transparent;color:var(--gray-500);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-family:'Inter',sans-serif;font-weight:500;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>

        <!-- Help Section -->
        <div style="background:#F0FDF4;border-radius:var(--radius);padding:16px 20px;margin-top:20px;border:1px solid #A7F3D0;">
            <h4 style="font-size:0.85rem;font-weight:600;color:#065F46;margin:0 0 8px;">
                <i class="fas fa-info-circle"></i> EC8B Form Instructions
            </h4>
            <ul style="font-size:0.8rem;color:#065F46;margin:0;padding-left:20px;">
                <li>EC8B is the Ward Collation Form for election results</li>
                <li>Upload a clear photo or scanned copy of the completed form</li>
                <li>Enter the party votes exactly as recorded on the form</li>
                <li>Verify that valid votes + rejected votes = total votes</li>
                <li>All fields are required for accurate record keeping</li>
            </ul>
        </div>
    </div>
</main>

<style>
.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

#dropZone {
    position: relative;
    cursor: pointer;
}

#dropZone:hover {
    border-color: var(--primary);
    background: var(--gray-50);
}

#dropZone.dragover {
    border-color: var(--primary);
    background: rgba(var(--primary-rgb), 0.05);
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-secondary:hover {
    background: var(--gray-200);
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    div[style*="grid-template-columns:1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script>
// ============================================================
// FILE UPLOAD HANDLING
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var dropZone = document.getElementById('dropZone');
    var fileInput = document.getElementById('fileInput');
    var filePreview = document.getElementById('filePreview');
    var fileName = document.getElementById('fileName');
    
    if (dropZone && fileInput) {
        dropZone.addEventListener('click', function(e) {
            if (e.target === this || e.target.tagName === 'I' || e.target.tagName === 'P') {
                fileInput.click();
            }
        });
        
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
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                handleFileSelect(fileInput.files[0]);
            }
        });
        
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                handleFileSelect(this.files[0]);
            }
        });
    }
    
    function handleFileSelect(file) {
        var preview = document.getElementById('filePreview');
        var nameDisplay = document.getElementById('fileName');
        
        if (preview && nameDisplay) {
            nameDisplay.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
            preview.style.display = 'block';
            
            // Update drop zone style
            var dropZone = document.getElementById('dropZone');
            if (dropZone) {
                dropZone.style.borderColor = '#10B981';
                dropZone.style.background = '#F0FDF4';
            }
        }
    }
    
    window.clearFile = function() {
        var preview = document.getElementById('filePreview');
        var input = document.getElementById('fileInput');
        var dropZone = document.getElementById('dropZone');
        
        if (preview) preview.style.display = 'none';
        if (input) input.value = '';
        if (dropZone) {
            dropZone.style.borderColor = '';
            dropZone.style.background = '';
        }
    };
});

// ============================================================
// LOCATION DROPDOWN CHAINING
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var stateSelect = document.getElementById('stateSelect');
    var lgaSelect = document.getElementById('lgaSelect');
    var wardSelect = document.getElementById('wardSelect');
    
    if (stateSelect) {
        stateSelect.addEventListener('change', function() {
            var stateId = this.value;
            if (stateId) {
                fetch('ajax-get-lgas.php?state_id=' + stateId)
                    .then(response => response.json())
                    .then(data => {
                        lgaSelect.innerHTML = '<option value="">Select LGA...</option>';
                        data.forEach(function(lga) {
                            lgaSelect.innerHTML += '<option value="' + lga.id + '">' + lga.name + '</option>';
                        });
                        wardSelect.innerHTML = '<option value="">Select Ward...</option>';
                    })
                    .catch(error => console.error('Error:', error));
            } else {
                lgaSelect.innerHTML = '<option value="">Select LGA...</option>';
                wardSelect.innerHTML = '<option value="">Select Ward...</option>';
            }
        });
    }
    
    if (lgaSelect) {
        lgaSelect.addEventListener('change', function() {
            var lgaId = this.value;
            if (lgaId) {
                fetch('ajax-get-wards.php?lga_id=' + lgaId)
                    .then(response => response.json())
                    .then(data => {
                        wardSelect.innerHTML = '<option value="">Select Ward...</option>';
                        data.forEach(function(ward) {
                            wardSelect.innerHTML += '<option value="' + ward.id + '">' + ward.name + '</option>';
                        });
                    })
                    .catch(error => console.error('Error:', error));
            } else {
                wardSelect.innerHTML = '<option value="">Select Ward...</option>';
            }
        });
    }
});

// ============================================================
// AUTO-CALCULATE TOTAL VOTES
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var validVotes = document.querySelector('input[name="valid_votes"]');
    var rejectedVotes = document.querySelector('input[name="rejected_votes"]');
    var totalVotes = document.querySelector('input[name="total_votes"]');
    
    function calculateTotal() {
        var valid = parseInt(validVotes?.value) || 0;
        var rejected = parseInt(rejectedVotes?.value) || 0;
        if (totalVotes) {
            totalVotes.value = valid + rejected;
        }
    }
    
    if (validVotes) {
        validVotes.addEventListener('input', calculateTotal);
    }
    if (rejectedVotes) {
        rejectedVotes.addEventListener('input', calculateTotal);
    }
});

// ============================================================
// SIDEBAR TOGGLE, DROPDOWNS, PROFILE, SEARCH
// ============================================================
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