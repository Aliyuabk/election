<?php
// ============================================================
// WARD COORDINATOR - PROFILE
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

// Only Ward coordinator can access
if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$user_email = SessionManager::get('user_email');
$ward_id = SessionManager::get('ward_id');
$lga_id = SessionManager::get('lga_id');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

// If ward_id is not set in session, try to get it from user record
if (empty($ward_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT ward_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['ward_id'])) {
            $ward_id = $user['ward_id'];
            SessionManager::set('ward_id', $ward_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching ward_id: " . $e->getMessage());
    }
}

$db = getDB();

// ============================================================
// FETCH USER DETAILS
// ============================================================
$user_details = null;

try {
    $stmt = $db->prepare("
        SELECT 
            u.*,
            r.name as role_name,
            r.level as role_level,
            w.name as ward_name,
            l.name as lga_name,
            s.name as state_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN wards w ON u.ward_id = w.id
        LEFT JOIN lgas l ON u.lga_id = l.id
        LEFT JOIN states s ON u.state_id = s.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user_details = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching user details: " . $e->getMessage());
}

// ============================================================
// HANDLE PROFILE UPDATE
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'update_profile') {
        $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $gender = isset($_POST['gender']) ? $_POST['gender'] : '';
        $date_of_birth = isset($_POST['date_of_birth']) ? $_POST['date_of_birth'] : '';
        $residential_address = isset($_POST['residential_address']) ? trim($_POST['residential_address']) : '';
        $emergency_contact_name = isset($_POST['emergency_contact_name']) ? trim($_POST['emergency_contact_name']) : '';
        $emergency_contact_phone = isset($_POST['emergency_contact_phone']) ? trim($_POST['emergency_contact_phone']) : '';
        $nin = isset($_POST['nin']) ? trim($_POST['nin']) : '';
        $bank_name = isset($_POST['bank_name']) ? trim($_POST['bank_name']) : '';
        $account_number = isset($_POST['account_number']) ? trim($_POST['account_number']) : '';
        $account_name = isset($_POST['account_name']) ? trim($_POST['account_name']) : '';
        
        if (empty($first_name) || empty($last_name)) {
            $error_message = "First name and last name are required.";
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET first_name = ?, last_name = ?, phone = ?, gender = ?, date_of_birth = ?,
                        residential_address = ?, emergency_contact_name = ?, emergency_contact_phone = ?,
                        nin = ?, bank_name = ?, account_number = ?, account_name = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $first_name, $last_name, $phone, $gender, $date_of_birth,
                    $residential_address, $emergency_contact_name, $emergency_contact_phone,
                    $nin, $bank_name, $account_number, $account_name,
                    $user_id
                ]);
                
                // Update session
                SessionManager::set('user_name', $first_name . ' ' . $last_name);
                
                logActivity($user_id, 'profile_updated', "Updated profile information", 'user', $user_id);
                $success_message = "Profile updated successfully!";
                
                // Refresh user details
                $stmt = $db->prepare("
                    SELECT 
                        u.*,
                        r.name as role_name,
                        r.level as role_level,
                        w.name as ward_name,
                        l.name as lga_name,
                        s.name as state_name
                    FROM users u
                    LEFT JOIN roles r ON u.role_id = r.id
                    LEFT JOIN wards w ON u.ward_id = w.id
                    LEFT JOIN lgas l ON u.lga_id = l.id
                    LEFT JOIN states s ON u.state_id = s.id
                    WHERE u.id = ?
                ");
                $stmt->execute([$user_id]);
                $user_details = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                $error_message = "Error updating profile: " . $e->getMessage();
                error_log("Profile update error: " . $e->getMessage());
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "Please fill in all password fields.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $error_message = "Password must be at least 8 characters.";
        } else {
            // Verify current password
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && verifyPassword($current_password, $user['password_hash'])) {
                $new_hash = hashPassword($new_password);
                $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_hash, $user_id]);
                
                logActivity($user_id, 'password_change', "Password changed successfully", 'user', $user_id);
                $success_message = "Password changed successfully!";
            } else {
                $error_message = "Current password is incorrect.";
            }
        }
    } elseif ($action === 'update_photo') {
        // Handle photo upload
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_photo'];
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            
            if (!in_array($file['type'], $allowed)) {
                $error_message = "Only JPG, PNG, GIF, and WEBP images are allowed.";
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error_message = "Image size must be less than 5MB.";
            } else {
                $upload_dir = '../../uploads/profiles/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $filename = 'profile_' . $user_id . '_' . time() . '.jpg';
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $photograph_url = '/election/uploads/profiles/' . $filename;
                    $stmt = $db->prepare("UPDATE users SET photograph_url = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$photograph_url, $user_id]);
                    
                    logActivity($user_id, 'profile_photo_updated', "Profile photo updated", 'user', $user_id);
                    $success_message = "Profile photo updated successfully!";
                    
                    // Refresh user details
                    $stmt = $db->prepare("
                        SELECT 
                            u.*,
                            r.name as role_name,
                            r.level as role_level,
                            w.name as ward_name,
                            l.name as lga_name,
                            s.name as state_name
                        FROM users u
                        LEFT JOIN roles r ON u.role_id = r.id
                        LEFT JOIN wards w ON u.ward_id = w.id
                        LEFT JOIN lgas l ON u.lga_id = l.id
                        LEFT JOIN states s ON u.state_id = s.id
                        WHERE u.id = ?
                    ");
                    $stmt->execute([$user_id]);
                    $user_details = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error_message = "Failed to upload image.";
                }
            }
        }
    }
}

$page_title = 'My Profile';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.profile-container {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 20px;
}

.profile-sidebar {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
    text-align: center;
}
.profile-sidebar .avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: var(--gray-200);
    margin: 0 auto 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--gray-600);
    overflow: hidden;
    border: 3px solid var(--primary);
}
.profile-sidebar .avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.profile-sidebar .name {
    font-size: 1.1rem;
    font-weight: 700;
}
.profile-sidebar .role {
    font-size: 0.85rem;
    color: var(--primary);
    font-weight: 500;
}
.profile-sidebar .location {
    font-size: 0.78rem;
    color: var(--gray-500);
    margin-top: 4px;
}
.profile-sidebar .user-code {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 2px;
}
.profile-sidebar .status-badge {
    display: inline-block;
    padding: 2px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 500;
    margin-top: 8px;
}
.profile-sidebar .status-badge.active { background: #ECFDF5; color: #10B981; }
.profile-sidebar .status-badge.suspended { background: #FEF2F2; color: #EF4444; }
.profile-sidebar .status-badge.pending { background: #FFFBEB; color: #F59E0B; }

.profile-sidebar .photo-upload {
    margin-top: 12px;
}
.profile-sidebar .photo-upload label {
    display: inline-block;
    padding: 6px 14px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.75rem;
    cursor: pointer;
    transition: var(--transition);
}
.profile-sidebar .photo-upload label:hover {
    background: var(--gray-50);
}
.profile-sidebar .photo-upload input[type="file"] {
    display: none;
}

.profile-content {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
}
.profile-content .tab-nav {
    display: flex;
    gap: 4px;
    border-bottom: 1px solid var(--gray-200);
    margin-bottom: 20px;
}
.profile-content .tab-nav .tab-btn {
    padding: 8px 16px;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--gray-500);
    transition: var(--transition);
    border-bottom: 2px solid transparent;
}
.profile-content .tab-nav .tab-btn:hover {
    color: var(--gray-700);
}
.profile-content .tab-nav .tab-btn.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}
.profile-content .tab-panel {
    display: none;
}
.profile-content .tab-panel.active {
    display: block;
}

.form-group {
    margin-bottom: 14px;
}
.form-group label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.form-group label .required {
    color: #EF4444;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
}
.form-group textarea {
    resize: vertical;
    min-height: 60px;
}
.form-group .helper {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 4px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.alert {
    padding: 12px 16px;
    border-radius: var(--radius);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success {
    background: #ECFDF5;
    border: 1px solid #D1FAE5;
    color: #065F46;
}
.alert-danger {
    background: #FEF2F2;
    border: 1px solid #FEE2E2;
    color: #991B1B;
}
.alert i {
    font-size: 1.1rem;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--gray-200);
}

@media (max-width: 768px) {
    .profile-container {
        grid-template-columns: 1fr;
    }
    .form-row {
        grid-template-columns: 1fr;
    }
    .form-actions {
        flex-direction: column;
    }
    .form-actions button,
    .form-actions a {
        width: 100%;
        text-align: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="broadcast-header" style="margin-bottom:16px;">
            <div>
                <h2><i class="fas fa-user-circle"></i> My Profile</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    Manage your account settings and personal information
                </p>
            </div>
            <div>
                <a href="../client-admin/" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($user_details): ?>
        <div class="profile-container">
            <!-- Sidebar -->
            <div class="profile-sidebar">
                <div class="avatar">
                    <?php if (!empty($user_details['photograph_url'])): ?>
                        <img src="<?php echo htmlspecialchars($user_details['photograph_url']); ?>" alt="<?php echo htmlspecialchars($user_details['full_name']); ?>">
                    <?php else: ?>
                        <?php echo strtoupper(substr($user_details['full_name'] ?? 'U', 0, 2)); ?>
                    <?php endif; ?>
                </div>
                <div class="name"><?php echo htmlspecialchars($user_details['full_name'] ?? 'User'); ?></div>
                <div class="role"><?php echo htmlspecialchars($user_details['role_name'] ?? 'Ward Coordinator'); ?></div>
                <div class="location">
                    <i class="fas fa-map-marker-alt"></i> 
                    <?php echo htmlspecialchars($user_details['ward_name'] ?? 'Ward'); ?>, 
                    <?php echo htmlspecialchars($user_details['lga_name'] ?? 'LGA'); ?>
                </div>
                <div class="user-code">Code: <?php echo htmlspecialchars($user_details['user_code'] ?? 'N/A'); ?></div>
                <div>
                    <span class="status-badge <?php echo $user_details['status'] ?? 'pending'; ?>">
                        <?php echo ucfirst($user_details['status'] ?? 'Pending'); ?>
                    </span>
                </div>
                
                <div class="photo-upload">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_photo">
                        <label for="profile_photo">
                            <i class="fas fa-camera"></i> Update Photo
                        </label>
                        <input type="file" name="profile_photo" id="profile_photo" accept="image/*" onchange="this.form.submit()">
                    </form>
                </div>
                
                <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--gray-200);">
                    <div style="font-size:0.7rem;color:var(--gray-400);">
                        <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user_details['email'] ?? 'N/A'); ?></div>
                        <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user_details['phone'] ?? 'N/A'); ?></div>
                        <div><i class="fas fa-calendar"></i> Joined <?php echo date('M d, Y', strtotime($user_details['created_at'] ?? 'now')); ?></div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="profile-content">
                <!-- Tab Navigation -->
                <div class="tab-nav">
                    <button class="tab-btn active" data-tab="profile" onclick="switchTab('profile')">
                        <i class="fas fa-user"></i> Profile
                    </button>
                    <button class="tab-btn" data-tab="password" onclick="switchTab('password')">
                        <i class="fas fa-key"></i> Password
                    </button>
                    <button class="tab-btn" data-tab="security" onclick="switchTab('security')">
                        <i class="fas fa-shield-alt"></i> Security
                    </button>
                </div>

                <!-- Tab: Profile -->
                <div class="tab-panel active" id="tab-profile">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name <span class="required">*</span></label>
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($user_details['first_name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name <span class="required">*</span></label>
                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($user_details['last_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="phone" value="<?php echo htmlspecialchars($user_details['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Gender</label>
                                <select name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo ($user_details['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo ($user_details['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo ($user_details['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                    <option value="prefer_not_say" <?php echo ($user_details['gender'] ?? '') === 'prefer_not_say' ? 'selected' : ''; ?>>Prefer not to say</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($user_details['date_of_birth'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>NIN (National Identification Number)</label>
                                <input type="text" name="nin" value="<?php echo htmlspecialchars($user_details['nin'] ?? ''); ?>" maxlength="11">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Residential Address</label>
                            <textarea name="residential_address" rows="2"><?php echo htmlspecialchars($user_details['residential_address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--gray-200);">
                            <h4 style="font-size:0.9rem;margin:0 0 12px;">Emergency Contact</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Emergency Contact Name</label>
                                    <input type="text" name="emergency_contact_name" value="<?php echo htmlspecialchars($user_details['emergency_contact_name'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Emergency Contact Phone</label>
                                    <input type="text" name="emergency_contact_phone" value="<?php echo htmlspecialchars($user_details['emergency_contact_phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--gray-200);">
                            <h4 style="font-size:0.9rem;margin:0 0 12px;">Banking Information</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Bank Name</label>
                                    <input type="text" name="bank_name" value="<?php echo htmlspecialchars($user_details['bank_name'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Account Number</label>
                                    <input type="text" name="account_number" value="<?php echo htmlspecialchars($user_details['account_number'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Account Name</label>
                                <input type="text" name="account_name" value="<?php echo htmlspecialchars($user_details['account_name'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tab: Password -->
                <div class="tab-panel" id="tab-password">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label>Current Password <span class="required">*</span></label>
                            <input type="password" name="current_password" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>New Password <span class="required">*</span></label>
                                <input type="password" name="new_password" required minlength="8">
                                <div class="helper">Password must be at least 8 characters</div>
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password <span class="required">*</span></label>
                                <input type="password" name="confirm_password" required>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tab: Security -->
                <div class="tab-panel" id="tab-security">
                    <div style="margin-bottom:16px;">
                        <h4 style="font-size:0.9rem;margin:0 0 8px;">Two-Factor Authentication</h4>
                        <p style="font-size:0.85rem;color:var(--gray-500);">
                            <?php if ($user_details['two_factor_enabled'] ?? 0): ?>
                                <span style="color:#10B981;"><i class="fas fa-check-circle"></i> Enabled</span>
                                - Two-factor authentication is active on your account.
                            <?php else: ?>
                                <span style="color:var(--gray-400);"><i class="fas fa-circle"></i> Disabled</span>
                                - Add an extra layer of security to your account.
                            <?php endif; ?>
                        </p>
                        <a href="two-factor-toggle.php" class="btn-secondary-sm">
                            <?php echo ($user_details['two_factor_enabled'] ?? 0) ? 'Disable 2FA' : 'Enable 2FA'; ?>
                        </a>
                    </div>
                    
                    <div style="margin-bottom:16px;padding-top:16px;border-top:1px solid var(--gray-200);">
                        <h4 style="font-size:0.9rem;margin:0 0 8px;">Active Sessions</h4>
                        <p style="font-size:0.85rem;color:var(--gray-500);">Manage your active sessions across devices.</p>
                        <a href="sessions.php" class="btn-secondary-sm">
                            <i class="fas fa-laptop"></i> Manage Sessions
                        </a>
                    </div>
                    
                    <div style="padding-top:16px;border-top:1px solid var(--gray-200);">
                        <h4 style="font-size:0.9rem;margin:0 0 8px;">Activity Log</h4>
                        <p style="font-size:0.85rem;color:var(--gray-500);">View your recent account activity.</p>
                        <a href="activity-logs.php" class="btn-secondary-sm">
                            <i class="fas fa-history"></i> View Activity Log
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Switch tabs
function switchTab(tabName) {
    // Hide all panels
    document.querySelectorAll('.tab-panel').forEach(function(panel) {
        panel.classList.remove('active');
    });
    
    // Show selected panel
    const panel = document.getElementById('tab-' + tabName);
    if (panel) {
        panel.classList.add('active');
    }
    
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.classList.remove('active');
        if (btn.dataset.tab === tabName) {
            btn.classList.add('active');
        }
    });
}

// Preloader
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
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

// Sidebar dropdowns
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

// Profile dropdown
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