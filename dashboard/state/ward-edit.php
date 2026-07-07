<?php
// ============================================================
// STATE COORDINATOR - EDIT WARD
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

// Get Ward ID from URL
$ward_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($ward_id <= 0) {
    header('Location: monitor-lgas.php?error=invalid_ward');
    exit();
}

$db = getDB();

// ============================================================
// FETCH WARD DATA
// ============================================================
$ward_data = null;
$lga_id = 0;
$lga_name = '';
$state_name = '';

try {
    $stmt = $db->prepare("
        SELECT 
            w.*,
            l.name as lga_name,
            l.id as lga_id,
            s.name as state_name
        FROM wards w
        JOIN lgas l ON w.lga_id = l.id
        JOIN states s ON l.state_id = s.id
        WHERE w.id = ? AND l.state_id = ?
    ");
    $stmt->execute([$ward_id, $state_id]);
    $ward_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ward_data) {
        header('Location: monitor-lgas.php?error=ward_not_found');
        exit();
    }
    
    $lga_id = $ward_data['lga_id'];
    $lga_name = $ward_data['lga_name'];
    $state_name = $ward_data['state_name'];
    
} catch (Exception $e) {
    error_log("Ward Edit Error: " . $e->getMessage());
    header('Location: monitor-lgas.php?error=database_error');
    exit();
}

// ============================================================
// PROCESS FORM SUBMISSION
// ============================================================
$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $registered_voters = intval($_POST['registered_voters'] ?? 0);
    $gps_lat = !empty($_POST['gps_lat']) ? floatval($_POST['gps_lat']) : null;
    $gps_lng = !empty($_POST['gps_lng']) ? floatval($_POST['gps_lng']) : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($name)) {
        $error = 'Ward name is required';
    } elseif (empty($code)) {
        $error = 'Ward code is required';
    } else {
        try {
            // Check if code already exists in this LGA (excluding current)
            $stmt = $db->prepare("SELECT id FROM wards WHERE lga_id = ? AND code = ? AND id != ?");
            $stmt->execute([$lga_id, $code, $ward_id]);
            if ($stmt->fetch()) {
                $error = 'Ward code already exists in this LGA';
            } else {
                $stmt = $db->prepare("
                    UPDATE wards 
                    SET code = ?,
                        name = ?,
                        registered_voters = ?,
                        gps_lat = ?,
                        gps_lng = ?,
                        is_active = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $code,
                    $name,
                    $registered_voters,
                    $gps_lat,
                    $gps_lng,
                    $is_active,
                    $ward_id
                ]);
                
                // Log activity
                $log_stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, entity_type, entity_id, created_at)
                    VALUES (?, ?, 'ward_updated', ?, 'ward', ?, NOW())
                ");
                $log_stmt->execute([
                    $user_id,
                    $tenant_id,
                    "Updated ward: $name in $lga_name",
                    $ward_id
                ]);
                
                $success = true;
                $message = "Ward updated successfully!";
                
                // Refresh data
                $stmt = $db->prepare("
                    SELECT 
                        w.*,
                        l.name as lga_name,
                        l.id as lga_id,
                        s.name as state_name
                    FROM wards w
                    JOIN lgas l ON w.lga_id = l.id
                    JOIN states s ON l.state_id = s.id
                    WHERE w.id = ? AND l.state_id = ?
                ");
                $stmt->execute([$ward_id, $state_id]);
                $ward_data = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $error = 'Failed to update ward: ' . $e->getMessage();
            error_log("Ward Update Error: " . $e->getMessage());
        }
    }
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Edit Ward';
$page_subtitle = $ward_data['name'] ?? 'Ward';
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
                <a href="monitor-lgas.php" style="text-decoration:none;color:var(--gray-500);">Monitor LGAs</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="lga-dashboard.php?id=<?php echo $lga_id; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($lga_name); ?></a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="manage-wards.php?lga=<?php echo $lga_id; ?>" style="text-decoration:none;color:var(--gray-500);">Manage Wards</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);"><?php echo htmlspecialchars($ward_data['name']); ?></span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-edit" style="color:var(--primary);"></i>
                        Edit Ward
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-layer-group"></i> 
                        <?php echo htmlspecialchars($ward_data['name']); ?> • 
                        <?php echo htmlspecialchars($lga_name); ?> LGA
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="manage-wards.php?lga=<?php echo $lga_id; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <a href="ward-dashboard.php?id=<?php echo $ward_id; ?>" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
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
                    <!-- Name -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Ward Name <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="text" name="name" class="form-control" required
                               value="<?php echo htmlspecialchars($ward_data['name']); ?>"
                               placeholder="Enter ward name"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    
                    <!-- Code -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Ward Code <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="text" name="code" class="form-control" required
                               value="<?php echo htmlspecialchars($ward_data['code']); ?>"
                               placeholder="Enter unique ward code"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                        <div style="font-size:0.65rem;color:var(--gray-400);margin-top:4px;">
                            <i class="fas fa-info-circle"></i> Unique code for this ward within the LGA
                        </div>
                    </div>
                    
                    <!-- Registered Voters -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Registered Voters
                        </label>
                        <input type="number" name="registered_voters" class="form-control"
                               value="<?php echo htmlspecialchars($ward_data['registered_voters'] ?? 0); ?>"
                               min="0"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                </div>
                
                <!-- Right Column -->
                <div>
                    <!-- GPS Coordinates -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            GPS Coordinates
                        </label>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                            <div>
                                <label style="display:block;font-size:0.65rem;color:var(--gray-400);">Latitude</label>
                                <input type="number" name="gps_lat" class="form-control"
                                       value="<?php echo htmlspecialchars($ward_data['gps_lat'] ?? ''); ?>"
                                       step="0.00000001"
                                       placeholder="e.g., 8.987654"
                                       style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;transition:var(--transition);">
                            </div>
                            <div>
                                <label style="display:block;font-size:0.65rem;color:var(--gray-400);">Longitude</label>
                                <input type="number" name="gps_lng" class="form-control"
                                       value="<?php echo htmlspecialchars($ward_data['gps_lng'] ?? ''); ?>"
                                       step="0.00000001"
                                       placeholder="e.g., 7.123456"
                                       style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.8rem;transition:var(--transition);">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Options -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Options
                        </label>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;">
                            <label style="display:flex;align-items:center;gap:6px;font-size:0.8rem;color:var(--gray-600);cursor:pointer;">
                                <input type="checkbox" name="is_active" value="1" <?php echo $ward_data['is_active'] ? 'checked' : ''; ?>>
                                <i class="fas fa-check-circle" style="color:#3B82F6;"></i> Active
                            </label>
                        </div>
                    </div>
                    
                    <!-- Location Info -->
                    <div style="padding:12px 16px;background:var(--gray-50);border-radius:8px;border:1px solid var(--gray-200);">
                        <label style="display:block;font-weight:600;font-size:0.75rem;color:var(--gray-500);margin-bottom:4px;">
                            <i class="fas fa-map-marker-alt"></i> Location
                        </label>
                        <div style="font-size:0.85rem;color:var(--gray-700);">
                            <?php echo htmlspecialchars($lga_name); ?> LGA, <?php echo htmlspecialchars($state_name); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div style="display:flex;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-200);flex-wrap:wrap;">
                <button type="submit" class="btn-primary" style="padding:10px 32px;background:var(--primary);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-save"></i> Update Ward
                </button>
                <button type="reset" class="btn-secondary" style="padding:10px 32px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-weight:500;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <a href="manage-wards.php?lga=<?php echo $lga_id; ?>" class="btn-secondary" style="padding:10px 32px;background:transparent;color:var(--gray-500);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-family:'Inter',sans-serif;font-weight:500;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
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
                Deleting this ward will remove all associated data including polling units, results, and check-ins.
            </p>
            <a href="ward-delete.php?id=<?php echo $ward_id; ?>" class="btn-danger" style="padding:8px 20px;background:#EF4444;color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;" onclick="return confirm('Are you sure you want to delete this ward? This action cannot be undone!')">
                <i class="fas fa-trash"></i> Delete Ward
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