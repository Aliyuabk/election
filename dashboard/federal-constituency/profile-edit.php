<?php
// ============================================================
// FEDERAL CONSTITUENCY COORDINATOR - EDIT PROFILE
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'federal_constituency') {
    header('Location: ../client-admin/');
    exit();
}

$user_id = SessionManager::get('user_id');
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
    
    if (empty($first_name) || empty($last_name)) {
        $error = 'First name and last name are required.';
    } elseif (empty($phone)) {
        $error = 'Phone number is required.';
    } else {
        try {
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
            logActivity($user_id, 'profile_updated', 'Updated profile information');
            
            // Refresh user data
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            SessionManager::set('user_name', $first_name . ' ' . $last_name);
            
        } catch (Exception $e) {
            $error = 'Failed to update profile: ' . $e->getMessage();
            error_log("Profile update error: " . $e->getMessage());
        }
    }
}

$page_title = 'Edit Profile';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.form-container {
    max-width: 800px;
    margin: 0 auto;
}
.form-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 30px;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.82rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.form-group label .required {
    color: #DC2626;
}
.form-group .help-text {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 4px;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    transition: var(--transition);
    background: var(--gray-50);
    color: var(--gray-700);
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}
.form-group textarea {
    min-height: 80px;
    resize: vertical;
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
.btn-primary {
    background: var(--primary);
    color: white;
}
.btn-primary:hover {
    background: var(--primary-dark);
}
.btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.btn-secondary:hover {
    background: var(--gray-200);
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

.alert {
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success {
    background: #ECFDF5;
    color: #065F46;
    border: 1px solid #A7F3D0;
}
.alert-error {
    background: #FEF2F2;
    color: #DC2626;
    border: 1px solid #FECACA;
}

@media (max-width: 768px) {
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
    .photo-upload {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="form-container">
            <h2 style="font-size:1.3rem;font-weight:700;margin-bottom:4px;">
                <i class="fas fa-user-edit" style="color:var(--primary);"></i> Edit Profile
            </h2>
            <p style="color:var(--gray-500);font-size:0.9rem;margin-bottom:20px;">
                Update your personal information and contact details.
            </p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo htmlspecialchars($success); ?></div>
                </div>
            <?php endif; ?>

            <div class="form-card">
                <form method="POST" enctype="multipart/form-data">
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
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="required">*</span></label>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <!-- Contact -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
                            <div class="help-text">Email cannot be changed. Contact support for assistance.</div>
                        </div>
                        <div class="form-group">
                            <label>Phone Number <span class="required">*</span></label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <!-- Personal Details -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender">
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo ($user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo ($user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                <option value="prefer_not_say" <?php echo ($user['gender'] ?? '') === 'prefer_not_say' ? 'selected' : ''; ?>>Prefer not to say</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="form-group">
                        <label>Residential Address</label>
                        <textarea name="residential_address" rows="3"><?php echo htmlspecialchars($user['residential_address'] ?? ''); ?></textarea>
                    </div>

                    <!-- Emergency Contact -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Emergency Contact Name</label>
                            <input type="text" name="emergency_contact_name" value="<?php echo htmlspecialchars($user['emergency_contact_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Emergency Contact Phone</label>
                            <input type="tel" name="emergency_contact_phone" value="<?php echo htmlspecialchars($user['emergency_contact_phone'] ?? ''); ?>">
                        </div>
                    </div>

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
    </div>
</main>

<script>
document.getElementById('profile_photo').addEventListener('change', function(e) {
    var file = e.target.files[0];
    if (file) {
        var reader = new FileReader();
        reader.onload = function(event) {
            var preview = document.querySelector('.photo-preview');
            preview.innerHTML = '<img src="' + event.target.result + '" alt="Profile Photo">';
        };
        reader.readAsDataURL(file);
    }
});

// Sidebar toggle (standard)
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