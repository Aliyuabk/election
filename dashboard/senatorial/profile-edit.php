<?php
// ============================================================
// SENATORIAL COORDINATOR - EDIT PROFILE
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

$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$db = getDB();

// Get user data
$user = null;
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching user: " . $e->getMessage());
}

if (!$user) {
    header('Location: profile.php');
    exit();
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $residential_address = trim($_POST['residential_address'] ?? '');
    $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
    $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');
    
    // Validate
    if (empty($first_name) || empty($last_name)) {
        $error = 'First name and last name are required.';
    } elseif (empty($phone)) {
        $error = 'Phone number is required.';
    } else {
        try {
            // Handle profile photo upload
            $photograph_url = $user['photograph_url'];
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/profiles/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                $filename = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $filepath)) {
                    $photograph_url = '/election/uploads/profiles/' . $filename;
                }
            }
            
            $stmt = $db->prepare("
                UPDATE users SET
                    first_name = ?,
                    last_name = ?,
                    phone = ?,
                    gender = ?,
                    date_of_birth = ?,
                    residential_address = ?,
                    emergency_contact_name = ?,
                    emergency_contact_phone = ?,
                    photograph_url = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $first_name,
                $last_name,
                $phone,
                $gender,
                $date_of_birth,
                $residential_address,
                $emergency_contact_name,
                $emergency_contact_phone,
                $photograph_url,
                $user_id
            ]);
            
            $success = 'Profile updated successfully!';
            
            // Refresh user data
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            // Update session
            SessionManager::set('user_name', $first_name . ' ' . $last_name);
            
            logActivity($user_id, 'profile_updated', 'Updated profile information');
            
        } catch (Exception $e) {
            error_log("Error updating profile: " . $e->getMessage());
            $error = 'Failed to update profile. Please try again.';
        }
    }
}

$page_title = 'Edit Profile';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.profile-edit-container {
    max-width: 800px;
    margin: 0 auto;
}
.profile-edit-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 30px;
    margin-bottom: 24px;
}
.profile-edit-header h2 {
    font-size: 1.2rem;
    font-weight: 600;
    margin: 0;
}
.profile-edit-header p {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin: 4px 0 0 0;
}
.profile-edit-form {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 30px;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 6px;
}
.form-group label .required {
    color: #EF4444;
}
.form-group .form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--gray-300);
    border-radius: 8px;
    font-size: 0.9rem;
    transition: var(--transition);
    background: white;
}
.form-group .form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}
.form-group .form-control.is-invalid {
    border-color: #EF4444;
}
.form-group .invalid-feedback {
    color: #EF4444;
    font-size: 0.75rem;
    margin-top: 4px;
}
.form-group .help-text {
    color: var(--gray-400);
    font-size: 0.7rem;
    margin-top: 4px;
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--gray-200);
}
.btn {
    padding: 10px 24px;
    border: none;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-primary {
    background: var(--primary);
    color: white;
}
.btn-primary:hover {
    background: #1D4ED8;
}
.btn-secondary {
    background: var(--gray-200);
    color: var(--gray-700);
}
.btn-secondary:hover {
    background: var(--gray-300);
}
.photo-upload {
    display: flex;
    align-items: center;
    gap: 20px;
}
.photo-preview {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid var(--gray-200);
    flex-shrink: 0;
}
.photo-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.photo-preview .placeholder {
    width: 100%;
    height: 100%;
    background: var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: var(--gray-500);
}
.photo-upload .upload-controls {
    flex: 1;
}
.photo-upload .upload-controls .file-input {
    display: none;
}
.photo-upload .upload-controls .btn-upload {
    display: inline-block;
    padding: 8px 16px;
    background: var(--gray-100);
    border: 1px dashed var(--gray-300);
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.8rem;
    color: var(--gray-600);
    transition: var(--transition);
}
.photo-upload .upload-controls .btn-upload:hover {
    border-color: var(--primary);
    color: var(--primary);
}

@media (max-width: 640px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    .form-actions {
        flex-direction: column;
    }
    .form-actions .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="profile-edit-container">
            <div class="profile-edit-header">
                <h2><i class="fas fa-user-edit"></i> Edit Profile</h2>
                <p>Update your personal information and contact details</p>
            </div>

            <?php if ($error): ?>
                <div style="background:#FEF2F2;border:1px solid #FEE2E2;color:#DC2626;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div style="background:#D1FAE5;border:1px solid #A7F3D0;color:#059669;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="profile-edit-form">
                <!-- Profile Photo -->
                <div class="form-group">
                    <label>Profile Photo</label>
                    <div class="photo-upload">
                        <div class="photo-preview">
                            <?php if (!empty($user['photograph_url'])): ?>
                                <img src="<?php echo htmlspecialchars($user['photograph_url']); ?>" alt="Profile Photo">
                            <?php else: ?>
                                <div class="placeholder">
                                    <?php echo substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'R', 0, 1); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="upload-controls">
                            <label class="btn-upload" for="profile_photo">
                                <i class="fas fa-camera"></i> Change Photo
                            </label>
                            <input type="file" id="profile_photo" name="profile_photo" class="file-input" accept="image/*">
                            <div class="help-text">Recommended: Square image, max 2MB</div>
                        </div>
                    </div>
                </div>

                <!-- Name -->
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                    </div>
                </div>

                <!-- Contact -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
                        <div class="help-text">Email cannot be changed. Contact support for assistance.</div>
                    </div>
                    <div class="form-group">
                        <label>Phone Number <span class="required">*</span></label>
                        <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                    </div>
                </div>

                <!-- Personal Details -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Gender</label>
                        <select class="form-control" name="gender">
                            <option value="">Select Gender</option>
                            <option value="male" <?php echo ($user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo ($user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                            <option value="prefer_not_say" <?php echo ($user['gender'] ?? '') === 'prefer_not_say' ? 'selected' : ''; ?>>Prefer not to say</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" class="form-control" name="date_of_birth" value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Address -->
                <div class="form-group">
                    <label>Residential Address</label>
                    <textarea class="form-control" name="residential_address" rows="3"><?php echo htmlspecialchars($user['residential_address'] ?? ''); ?></textarea>
                </div>

                <!-- Emergency Contact -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Emergency Contact Name</label>
                        <input type="text" class="form-control" name="emergency_contact_name" value="<?php echo htmlspecialchars($user['emergency_contact_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Emergency Contact Phone</label>
                        <input type="tel" class="form-control" name="emergency_contact_phone" value="<?php echo htmlspecialchars($user['emergency_contact_phone'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="profile.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
// Preview profile photo before upload
document.getElementById('profile_photo').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            const preview = document.querySelector('.photo-preview');
            preview.innerHTML = `<img src="${event.target.result}" alt="Profile Photo">`;
        };
        reader.readAsDataURL(file);
    }
});

// Sidebar toggle
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