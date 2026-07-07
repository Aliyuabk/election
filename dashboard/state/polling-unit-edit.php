<?php
// ============================================================
// STATE COORDINATOR - EDIT POLLING UNIT
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

// Only state coordinator can access
if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$user_email = SessionManager::get('user_email');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

// Get PU ID from URL
$pu_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($pu_id <= 0) {
    header('Location: polling-units.php?error=invalid_pu');
    exit();
}

$db = getDB();

// ============================================================
// FETCH POLLING UNIT DATA
// ============================================================
$pu_data = null;
$back_url = 'polling-units.php';

try {
    $stmt = $db->prepare("
        SELECT 
            pu.*,
            w.id as ward_id,
            w.name as ward_name,
            l.id as lga_id,
            l.name as lga_name,
            s.id as state_id,
            s.name as state_name
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        JOIN states s ON l.state_id = s.id
        WHERE pu.id = ? AND s.id = ?
    ");
    $stmt->execute([$pu_id, $state_id]);
    $pu_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pu_data) {
        header('Location: polling-units.php?error=pu_not_found');
        exit();
    }
    
    $back_url = "polling-units.php?ward=" . $pu_data['ward_id'];
    
} catch (Exception $e) {
    error_log("PU Edit Error: " . $e->getMessage());
    header('Location: polling-units.php?error=database_error');
    exit();
}

// ============================================================
// FETCH LOCATION DATA FOR DROPDOWNS
// ============================================================
$lgas = [];
$wards = [];

// Get LGAs for state
try {
    $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$state_id]);
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lgas = [];
}

// Get Wards for selected LGA
if ($pu_data['lga_id'] > 0) {
    try {
        $stmt = $db->prepare("SELECT id, name FROM wards WHERE lga_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$pu_data['lga_id']]);
        $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $wards = [];
    }
}

// ============================================================
// NETWORK QUALITY OPTIONS
// ============================================================
$network_options = [
    '' => 'Not Specified',
    '5g' => '5G',
    '4g' => '4G',
    '3g' => '3G',
    '2g' => '2G',
    'none' => 'No Network'
];

// ============================================================
// PROCESS FORM SUBMISSION
// ============================================================
$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ward_id_post = intval($_POST['ward_id'] ?? 0);
    $code = trim($_POST['code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $registered_voters = intval($_POST['registered_voters'] ?? 0);
    $gps_lat = !empty($_POST['gps_lat']) ? floatval($_POST['gps_lat']) : null;
    $gps_lng = !empty($_POST['gps_lng']) ? floatval($_POST['gps_lng']) : null;
    $gps_accuracy = !empty($_POST['gps_accuracy']) ? floatval($_POST['gps_accuracy']) : null;
    $is_rural = isset($_POST['is_rural']) ? 1 : 0;
    $network_quality = $_POST['network_quality'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if ($ward_id_post <= 0) {
        $error = 'Please select a ward';
    } elseif (empty($name)) {
        $error = 'Polling unit name is required';
    } elseif (empty($code)) {
        $error = 'Polling unit code is required';
    } else {
        try {
            // Check if code already exists for this ward (excluding current)
            $stmt = $db->prepare("SELECT id FROM polling_units WHERE ward_id = ? AND code = ? AND id != ?");
            $stmt->execute([$ward_id_post, $code, $pu_id]);
            if ($stmt->fetch()) {
                $error = 'Polling unit code already exists in this ward';
            } else {
                $stmt = $db->prepare("
                    UPDATE polling_units 
                    SET ward_id = ?,
                        code = ?,
                        name = ?,
                        description = ?,
                        address = ?,
                        registered_voters = ?,
                        gps_lat = ?,
                        gps_lng = ?,
                        gps_accuracy = ?,
                        is_rural = ?,
                        network_quality = ?,
                        is_active = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $ward_id_post,
                    $code,
                    $name,
                    $description,
                    $address,
                    $registered_voters,
                    $gps_lat,
                    $gps_lng,
                    $gps_accuracy,
                    $is_rural,
                    $network_quality,
                    $is_active,
                    $pu_id
                ]);
                
                // Log activity
                $log_stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, entity_type, entity_id, created_at)
                    VALUES (?, ?, 'pu_updated', ?, 'polling_unit', ?, NOW())
                ");
                $log_stmt->execute([
                    $user_id,
                    $tenant_id,
                    "Updated polling unit: $name ($code)",
                    $pu_id
                ]);
                
                $success = true;
                $message = "Polling unit updated successfully!";
                
                // Refresh data
                $stmt = $db->prepare("
                    SELECT 
                        pu.*,
                        w.id as ward_id,
                        w.name as ward_name,
                        l.id as lga_id,
                        l.name as lga_name,
                        s.id as state_id,
                        s.name as state_name
                    FROM polling_units pu
                    JOIN wards w ON pu.ward_id = w.id
                    JOIN lgas l ON w.lga_id = l.id
                    JOIN states s ON l.state_id = s.id
                    WHERE pu.id = ? AND s.id = ?
                ");
                $stmt->execute([$pu_id, $state_id]);
                $pu_data = $stmt->fetch(PDO::FETCH_ASSOC);
                $back_url = "polling-units.php?ward=" . $pu_data['ward_id'];
            }
        } catch (Exception $e) {
            $error = 'Failed to update polling unit: ' . $e->getMessage();
            error_log("PU Update Error: " . $e->getMessage());
        }
    }
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Edit Polling Unit';
$page_subtitle = $pu_data['name'] ?? 'Polling Unit';
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Breadcrumb -->
        <div class="welcome-section">
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <a href="index.php" style="text-decoration:none;color:var(--gray-500);">Dashboard</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="polling-units.php" style="text-decoration:none;color:var(--gray-500);">Polling Units</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Edit</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-edit" style="color:var(--primary);"></i>
                        Edit Polling Unit
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-flag-checkered"></i> 
                        <?php echo htmlspecialchars($pu_data['name']); ?> • 
                        <?php echo htmlspecialchars($pu_data['code']); ?>
                    </p>
                    <p style="color:var(--gray-400);font-size:0.75rem;margin:2px 0 0;">
                        <?php echo htmlspecialchars($pu_data['ward_name']); ?> • 
                        <?php echo htmlspecialchars($pu_data['lga_name']); ?> • 
                        <?php echo htmlspecialchars($pu_data['state_name']); ?>
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="<?php echo $back_url; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                    <a href="pu-dashboard.php?id=<?php echo $pu_id; ?>" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </div>
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

        <!-- Edit Form -->
        <form method="POST" action="" style="background:white;border-radius:var(--radius);padding:24px;border:1px solid var(--gray-200);">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <!-- Left Column -->
                <div>
                    <!-- Location -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Location <span style="color:#EF4444;">*</span>
                        </label>
                        
                        <!-- State (Read-only) -->
                        <div style="margin-bottom:8px;">
                            <input type="hidden" name="state_id" value="<?php echo $state_id; ?>">
                            <div style="padding:8px 12px;background:var(--gray-50);border-radius:8px;border:1px solid var(--gray-200);">
                                <label style="display:block;font-size:0.65rem;color:var(--gray-400);">State</label>
                                <div style="font-weight:500;color:var(--gray-700);"><?php echo htmlspecialchars($pu_data['state_name']); ?></div>
                            </div>
                        </div>
                        
                        <!-- LGA -->
                        <div style="margin-bottom:8px;">
                            <select name="lga_id" class="form-control" id="lgaSelect"
                                    style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;transition:var(--transition);">
                                <option value="">Select LGA...</option>
                                <?php foreach ($lgas as $lga): ?>
                                    <option value="<?php echo $lga['id']; ?>" 
                                        <?php echo $pu_data['lga_id'] == $lga['id'] ? 'selected' : ''; ?>>
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
                                        <?php echo $pu_data['ward_id'] == $ward['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ward['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Code -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Polling Unit Code <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="text" name="code" class="form-control" required
                               value="<?php echo htmlspecialchars($pu_data['code'] ?? ''); ?>"
                               placeholder="e.g., PU-001"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    
                    <!-- Name -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Polling Unit Name <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="text" name="name" class="form-control" required
                               value="<?php echo htmlspecialchars($pu_data['name'] ?? ''); ?>"
                               placeholder="Enter polling unit name"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    
                    <!-- Description -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Description
                        </label>
                        <textarea name="description" class="form-control" rows="2"
                                  placeholder="Additional description..."
                                  style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;resize:vertical;transition:var(--transition);"><?php echo htmlspecialchars($pu_data['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div>
                    <!-- Address -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Address
                        </label>
                        <textarea name="address" class="form-control" rows="2"
                                  placeholder="Physical address of the polling unit"
                                  style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;resize:vertical;transition:var(--transition);"><?php echo htmlspecialchars($pu_data['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Registered Voters -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Registered Voters
                        </label>
                        <input type="number" name="registered_voters" class="form-control"
                               value="<?php echo htmlspecialchars($pu_data['registered_voters'] ?? 0); ?>"
                               min="0"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    
                    <!-- GPS Coordinates -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            GPS Coordinates
                        </label>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                            <div>
                                <label style="display:block;font-size:0.65rem;color:var(--gray-400);">Latitude</label>
                                <input type="number" name="gps_lat" class="form-control"
                                       value="<?php echo htmlspecialchars($pu_data['gps_lat'] ?? ''); ?>"
                                       step="0.00000001"
                                       placeholder="e.g., 8.987654"
                                       style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;transition:var(--transition);">
                            </div>
                            <div>
                                <label style="display:block;font-size:0.65rem;color:var(--gray-400);">Longitude</label>
                                <input type="number" name="gps_lng" class="form-control"
                                       value="<?php echo htmlspecialchars($pu_data['gps_lng'] ?? ''); ?>"
                                       step="0.00000001"
                                       placeholder="e.g., 7.123456"
                                       style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;transition:var(--transition);">
                            </div>
                        </div>
                        <div style="margin-top:4px;">
                            <label style="display:block;font-size:0.65rem;color:var(--gray-400);">GPS Accuracy (meters)</label>
                            <input type="number" name="gps_accuracy" class="form-control"
                                   value="<?php echo htmlspecialchars($pu_data['gps_accuracy'] ?? ''); ?>"
                                   step="0.01"
                                   placeholder="e.g., 5.00"
                                   style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;transition:var(--transition);">
                        </div>
                    </div>
                    
                    <!-- Options -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Options
                        </label>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                            <label style="display:flex;align-items:center;gap:6px;font-size:0.8rem;color:var(--gray-600);cursor:pointer;padding:6px 10px;background:var(--gray-50);border-radius:6px;">
                                <input type="checkbox" name="is_rural" value="1" <?php echo $pu_data['is_rural'] ? 'checked' : ''; ?>>
                                <i class="fas fa-tree" style="color:#10B981;"></i> Rural
                            </label>
                            
                            <label style="display:flex;align-items:center;gap:6px;font-size:0.8rem;color:var(--gray-600);cursor:pointer;padding:6px 10px;background:var(--gray-50);border-radius:6px;">
                                <input type="checkbox" name="is_active" value="1" <?php echo $pu_data['is_active'] ? 'checked' : ''; ?>>
                                <i class="fas fa-check-circle" style="color:#3B82F6;"></i> Active
                            </label>
                        </div>
                    </div>
                    
                    <!-- Network Quality -->
                    <div>
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Network Quality
                        </label>
                        <select name="network_quality" class="form-control"
                                style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                            <?php foreach ($network_options as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $pu_data['network_quality'] == $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div style="display:flex;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-200);flex-wrap:wrap;">
                <button type="submit" class="btn-primary" style="padding:10px 32px;background:var(--primary);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-save"></i> Update Polling Unit
                </button>
                <button type="reset" class="btn-secondary" style="padding:10px 32px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-weight:500;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <a href="<?php echo $back_url; ?>" class="btn-secondary" style="padding:10px 32px;background:transparent;color:var(--gray-500);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-family:'Inter',sans-serif;font-weight:500;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>

        <!-- Danger Zone -->
        <div style="background:#FEF2F2;border-radius:var(--radius);padding:16px 20px;margin-top:20px;border:1px solid #FECACA;">
            <h4 style="font-size:0.85rem;font-weight:600;color:#991B1B;margin:0 0 8px;">
                <i class="fas fa-exclamation-triangle"></i> Danger Zone
            </h4>
            <p style="font-size:0.8rem;color:#991B1B;margin:0 0 12px;">
                Deleting a polling unit will remove all associated data including results and check-ins.
            </p>
            <a href="polling-unit-delete.php?id=<?php echo $pu_id; ?>" class="btn-danger" style="padding:8px 20px;background:#EF4444;color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;" onclick="return confirm('Are you sure you want to delete this polling unit? This action cannot be undone!')">
                <i class="fas fa-trash"></i> Delete Polling Unit
            </a>
        </div>
    </div>
</main>

<style>
.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
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

.btn-danger:hover {
    background: #DC2626;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

@media (max-width: 768px) {
    div[style*="grid-template-columns:1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
    div[style*="grid-template-columns:1fr 1fr;gap:8px;"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script>
// ============================================================
// LOCATION DROPDOWN CHAINING
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var lgaSelect = document.getElementById('lgaSelect');
    var wardSelect = document.getElementById('wardSelect');
    
    // Store current values
    var currentLgaId = <?php echo $pu_data['lga_id']; ?>;
    var currentWardId = <?php echo $pu_data['ward_id']; ?>;
    
    if (lgaSelect) {
        lgaSelect.addEventListener('change', function() {
            var lgaId = this.value;
            if (lgaId) {
                fetch('ajax-get-wards.php?lga_id=' + lgaId)
                    .then(response => response.json())
                    .then(data => {
                        wardSelect.innerHTML = '<option value="">Select Ward...</option>';
                        data.forEach(function(ward) {
                            var selected = (ward.id == currentWardId) ? 'selected' : '';
                            wardSelect.innerHTML += '<option value="' + ward.id + '" ' + selected + '>' + ward.name + '</option>';
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