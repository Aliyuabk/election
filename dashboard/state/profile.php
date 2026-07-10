<?php
// ============================================================
// STATE COORDINATOR - USER PROFILE
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

if (empty($state_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT state_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['state_id'])) {
            $state_id = $user['state_id'];
            SessionManager::set('state_id', $state_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching state_id: " . $e->getMessage());
    }
}

$db = getDB();

// Get state name
$state_name = 'Unknown State';
if (!empty($state_id)) {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
}

// Get user data
$user_data = null;
try {
    $stmt = $db->prepare("
        SELECT u.*, r.name as role_name, r.level as role_level,
               l.name as lga_name, w.name as ward_name, pu.name as pu_name,
               s.name as state_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN lgas l ON u.lga_id = l.id
        LEFT JOIN wards w ON u.ward_id = w.id
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        LEFT JOIN states s ON u.state_id = s.id
        WHERE u.id = ? AND u.tenant_id = ?
    ");
    $stmt->execute([$user_id, $tenant_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching user data: " . $e->getMessage());
}

if (!$user_data) {
    header('Location: index.php');
    exit();
}

// Get user statistics
$user_stats = [
    'activities' => 0,
    'elections' => 0,
    'broadcasts' => 0,
    'incidents' => 0,
    'verified_results' => 0,
    'last_login' => null
];

try {
    // Activities
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM activity_logs WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_stats['activities'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Elections created
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM elections WHERE created_by = ? AND deleted_at IS NULL");
    $stmt->execute([$user_id]);
    $user_stats['elections'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Broadcasts created
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM broadcasts WHERE sender_id = ?");
    $stmt->execute([$user_id]);
    $user_stats['broadcasts'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Incidents reported
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM incidents WHERE reporter_id = ?");
    $stmt->execute([$user_id]);
    $user_stats['incidents'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Results verified
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM results_ec8a WHERE verified_by = ?");
    $stmt->execute([$user_id]);
    $user_stats['verified_results'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    $user_stats['last_login'] = $user_data['last_login_at'] ?? null;
    
} catch (Exception $e) {
    error_log("Error fetching user stats: " . $e->getMessage());
}

// Handle profile update
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'profile') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $gender = $_POST['gender'] ?? '';
        $date_of_birth = $_POST['date_of_birth'] ?? '';
        $address = trim($_POST['address'] ?? '');
        $nin = trim($_POST['nin'] ?? '');
        
        if (empty($first_name) || empty($last_name)) {
            $error = 'Name fields are required.';
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET first_name = ?, last_name = ?, phone = ?, 
                        gender = ?, date_of_birth = ?, 
                        residential_address = ?, nin = ?
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([
                    $first_name, $last_name, $phone,
                    $gender, !empty($date_of_birth) ? $date_of_birth : null,
                    $address, $nin,
                    $user_id, $tenant_id
                ]);
                
                // Update session
                SessionManager::set('user_name', $first_name . ' ' . $last_name);
                
                logActivity($user_id, 'profile_updated', 'Profile information updated');
                
                $message = 'Profile updated successfully!';
                
                // Refresh user data
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $error = 'Failed to update profile: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'photo') {
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_photo'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowed_types)) {
                $error = 'Invalid file type. Please upload JPEG, PNG, GIF, or WebP.';
            } elseif ($file['size'] > $max_size) {
                $error = 'File too large. Maximum size is 5MB.';
            } else {
                try {
                    $upload_dir = '../../uploads/profiles/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'profile_' . $user_id . '_' . time() . '.' . $extension;
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $db_path = '/election/uploads/profiles/' . $filename;
                        $stmt = $db->prepare("UPDATE users SET photograph_url = ? WHERE id = ?");
                        $stmt->execute([$db_path, $user_id]);
                        
                        logActivity($user_id, 'profile_photo_updated', 'Profile photo updated');
                        $message = 'Profile photo updated successfully!';
                        
                        // Refresh user data
                        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $error = 'Failed to upload photo.';
                    }
                } catch (Exception $e) {
                    $error = 'Failed to upload photo: ' . $e->getMessage();
                }
            }
        } else {
            $error = 'Please select a photo to upload.';
        }
    }
}

$full_name = ($user_data['first_name'] ?? '') . ' ' . ($user_data['last_name'] ?? '');
$initials = strtoupper(substr($user_data['first_name'] ?? '', 0, 1) . substr($user_data['last_name'] ?? '', 0, 1));

$page_title = 'My Profile';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.profile-container {
    max-width: 900px;
    margin: 0 auto;
}

.profile-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 28px 32px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
}

.profile-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 2.2rem;
    flex-shrink: 0;
    position: relative;
    overflow: hidden;
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-avatar .upload-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(0,0,0,0.6);
    color: white;
    text-align: center;
    padding: 4px;
    font-size: 0.55rem;
    cursor: pointer;
    opacity: 0;
    transition: var(--transition);
}

.profile-avatar:hover .upload-overlay {
    opacity: 1;
}

.profile-info h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}

.profile-info .role {
    color: var(--primary);
    font-weight: 500;
    font-size: 0.85rem;
}

.profile-info .location {
    color: var(--gray-500);
    font-size: 0.8rem;
}

.profile-info .badges {
    display: flex;
    gap: 8px;
    margin-top: 6px;
    flex-wrap: wrap;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.6rem;
    padding: 3px 12px;
    border-radius: 12px;
    font-weight: 600;
}

.status-badge .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.status-badge.active { background: #ECFDF5; color: #065F46; }
.status-badge.active .dot { background: #10B981; }

.settings-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
    margin-bottom: 16px;
}

.settings-card .card-title {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.settings-card .card-title i {
    color: var(--primary);
    margin-right: 6px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}

.form-group label .required {
    color: #EF4444;
    margin-left: 2px;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="tel"],
.form-group input[type="date"],
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    transition: var(--transition);
    background: white;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.form-group .help-text {
    font-size: 0.65rem;
    color: var(--gray-400);
    margin-top: 4px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.alert {
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
}

.alert-success {
    background: #ECFDF5;
    color: #065F46;
    border: 1px solid #A7F3D0;
}

.alert-error {
    background: #FEF2F2;
    color: #991B1B;
    border: 1px solid #FECACA;
}

.alert i {
    margin-right: 6px;
}

.btn-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-group button {
    padding: 10px 28px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-group .btn-save {
    background: var(--primary);
    color: white;
}

.btn-group .btn-save:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-group .btn-upload {
    background: #10B981;
    color: white;
}

.btn-group .btn-upload:hover {
    background: #059669;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 10px;
    margin-top: 8px;
}

.stats-grid .stat-item {
    text-align: center;
    padding: 10px;
    background: var(--gray-50);
    border-radius: 8px;
}

.stats-grid .stat-item .number {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--gray-800);
}

.stats-grid .stat-item .label {
    font-size: 0.6rem;
    color: var(--gray-500);
}

.file-input-wrapper {
    position: relative;
    overflow: hidden;
    display: inline-block;
}

.file-input-wrapper input[type="file"] {
    position: absolute;
    left: 0;
    top: 0;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
}

@media (max-width: 768px) {
    .profile-header {
        flex-direction: column;
        text-align: center;
        padding: 20px;
    }
    .form-row {
        grid-template-columns: 1fr;
    }
    .settings-card {
        padding: 16px 18px;
    }
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="profile-container">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar" id="avatarContainer">
                    <?php if (!empty($user_data['photograph_url'])): ?>
                        <img src="<?php echo htmlspecialchars($user_data['photograph_url']); ?>" alt="<?php echo htmlspecialchars($full_name); ?>" />
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                    <div class="upload-overlay" onclick="document.getElementById('photoUpload').click()">
                        <i class="fas fa-camera"></i> Change Photo
                    </div>
                </div>
                
                <div class="profile-info" style="flex:1;">
                    <h2><?php echo htmlspecialchars($full_name); ?></h2>
                    <div class="role">
                        <i class="fas fa-user-tag"></i> 
                        <?php echo htmlspecialchars($user_data['role_name'] ?? 'State Coordinator'); ?>
                    </div>
                    <div class="location">
                        <i class="fas fa-map-marker-alt"></i> 
                        <?php echo htmlspecialchars($user_data['state_name'] ?? $state_name); ?>
                        <?php if (!empty($user_data['lga_name'])): ?>
                            - <?php echo htmlspecialchars($user_data['lga_name']); ?>
                        <?php endif; ?>
                    </div>
                    <div class="badges">
                        <span class="status-badge active">
                            <span class="dot"></span>
                            Active
                        </span>
                        <?php if ($user_data['two_factor_enabled']): ?>
                            <span class="status-badge" style="background:#EFF6FF;color:#1E40AF;">
                                <i class="fas fa-shield-alt"></i> 2FA Enabled
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div style="margin-left:auto;">
                    <div class="stats-grid" style="min-width:200px;">
                        <div class="stat-item">
                            <div class="number"><?php echo number_format($user_stats['activities']); ?></div>
                            <div class="label">Activities</div>
                        </div>
                        <div class="stat-item">
                            <div class="number"><?php echo number_format($user_stats['elections']); ?></div>
                            <div class="label">Elections</div>
                        </div>
                        <div class="stat-item">
                            <div class="number"><?php echo number_format($user_stats['verified_results']); ?></div>
                            <div class="label">Verified</div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Upload Photo -->
            <div class="settings-card">
                <div class="card-title"><i class="fas fa-image"></i> Profile Photo</div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="photo" />
                    
                    <div class="form-group">
                        <div class="file-input-wrapper">
                            <button type="button" class="btn-upload" onclick="document.getElementById('photoUpload').click()">
                                <i class="fas fa-upload"></i> Choose Photo
                            </button>
                            <input type="file" name="profile_photo" id="photoUpload" accept="image/*" onchange="this.form.submit()" />
                        </div>
                        <div class="help-text">JPEG, PNG, GIF, WebP. Max 5MB.</div>
                    </div>
                </form>
            </div>

            <!-- Profile Information -->
            <div class="settings-card">
                <div class="card-title"><i class="fas fa-user"></i> Personal Information</div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="profile" />
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name <span class="required">*</span></label>
                            <input type="text" name="first_name" required value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>" />
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="required">*</span></label>
                            <input type="text" name="last_name" required value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>" />
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" disabled />
                            <div class="help-text">Email cannot be changed. Contact administrator for changes.</div>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" />
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender">
                                <option value="">Select...</option>
                                <option value="male" <?php echo ($user_data['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($user_data['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo ($user_data['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                <option value="prefer_not_say" <?php echo ($user_data['gender'] ?? '') === 'prefer_not_say' ? 'selected' : ''; ?>>Prefer not to say</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($user_data['date_of_birth'] ?? ''); ?>" />
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Residential Address</label>
                        <textarea name="address" rows="2"><?php echo htmlspecialchars($user_data['residential_address'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>NIN (National Identification Number)</label>
                        <input type="text" name="nin" value="<?php echo htmlspecialchars($user_data['nin'] ?? ''); ?>" />
                        <div class="help-text">Optional - for verification purposes</div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>

            <!-- Account Information -->
            <div class="settings-card">
                <div class="card-title"><i class="fas fa-info-circle"></i> Account Information</div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                    <div>
                        <div style="font-size:0.65rem;color:var(--gray-500);">User Code</div>
                        <div style="font-weight:500;font-size:0.85rem;"><?php echo htmlspecialchars($user_data['user_code'] ?? 'N/A'); ?></div>
                    </div>
                    <div>
                        <div style="font-size:0.65rem;color:var(--gray-500);">Role</div>
                        <div style="font-weight:500;font-size:0.85rem;"><?php echo htmlspecialchars($user_data['role_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div>
                        <div style="font-size:0.65rem;color:var(--gray-500);">Member Since</div>
                        <div style="font-weight:500;font-size:0.85rem;"><?php echo date('F j, Y', strtotime($user_data['created_at'] ?? 'now')); ?></div>
                    </div>
                    <div>
                        <div style="font-size:0.65rem;color:var(--gray-500);">Last Login</div>
                        <div style="font-weight:500;font-size:0.85rem;">
                            <?php echo $user_stats['last_login'] ? date('F j, Y g:i A', strtotime($user_stats['last_login'])) : 'Never'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Photo upload trigger
document.getElementById('photoUpload')?.addEventListener('change', function() {
    if (this.files && this.files[0]) {
        this.form.submit();
    }
});

// Same sidebar scripts as index.php
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