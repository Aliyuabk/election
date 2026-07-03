<?php
// ============================================================
// SECURITY - CLIENT ADMIN
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
// FETCH USER SECURITY INFO
// ============================================================
$user = null;
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$user_id, $tenant_id]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH SECURITY EVENTS
// ============================================================
$security_events = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM security_events 
        WHERE user_id = ? OR user_id IS NULL
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $security_events = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH ACTIVE SESSIONS
// ============================================================
$sessions = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM user_sessions 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    $sessions = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH LOGIN ATTEMPTS
// ============================================================
$login_attempts = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM login_attempts 
        WHERE user_id = ? OR email = ?
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$user_id, $user_email]);
    $login_attempts = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// ============================================================
// HANDLE SECURITY ACTIONS
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'change_password':
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                if (empty($current_password)) {
                    throw new Exception('Current password is required.');
                }
                if (empty($new_password)) {
                    throw new Exception('New password is required.');
                }
                if (strlen($new_password) < 8) {
                    throw new Exception('New password must be at least 8 characters.');
                }
                if ($new_password !== $confirm_password) {
                    throw new Exception('Passwords do not match.');
                }
                
                // Verify current password
                if (!password_verify($current_password, $user['password_hash'])) {
                    throw new Exception('Current password is incorrect.');
                }
                
                // Update password
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$new_hash, $user_id, $tenant_id]);
                
                logActivity($user_id, 'password_changed', 'Password changed successfully');
                logSecurityEvent($user_id, 'password_change', 'Password changed from IP: ' . getClientIP());
                
                $success = "Password changed successfully!";
                break;
                
            case 'enable_2fa':
                $two_factor_enabled = isset($_POST['two_factor_enabled']) ? 1 : 0;
                $stmt = $db->prepare("UPDATE users SET two_factor_enabled = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$two_factor_enabled, $user_id, $tenant_id]);
                
                $status = $two_factor_enabled ? 'enabled' : 'disabled';
                logActivity($user_id, '2fa_' . $status, "2FA $status");
                logSecurityEvent($user_id, '2fa_' . $status, "2FA $status from IP: " . getClientIP());
                
                $success = "Two-factor authentication $status successfully!";
                
                // Refresh user data
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$user_id, $tenant_id]);
                $user = $stmt->fetch();
                break;
                
            case 'logout_session':
                $session_id = (int)($_POST['session_id'] ?? 0);
                if ($session_id > 0) {
                    $stmt = $db->prepare("DELETE FROM user_sessions WHERE id = ? AND user_id = ?");
                    $stmt->execute([$session_id, $user_id]);
                    $success = "Session terminated successfully.";
                }
                break;
                
            case 'logout_all_sessions':
                // Keep current session
                $current_token = SessionManager::get('session_token');
                $stmt = $db->prepare("DELETE FROM user_sessions WHERE user_id = ? AND token != ?");
                $stmt->execute([$user_id, $current_token]);
                $success = "All other sessions terminated successfully.";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       SECURITY - CLIENT ADMIN STYLES
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
    
    .btn-outline {
        padding: 8px 16px;
        background: transparent;
        color: var(--gray-600);
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.82rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-outline:hover {
        background: var(--gray-50);
        border-color: var(--gray-300);
    }
    .btn-primary {
        padding: 8px 18px;
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
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25);
    }
    .btn-danger {
        padding: 8px 18px;
        background: var(--danger);
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
    .btn-danger:hover {
        background: #DC2626;
        transform: translateY(-1px);
    }
    .btn-sm {
        padding: 4px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-sm.danger { background: #FEF2F2; color: #991B1B; }
    .btn-sm.danger:hover { background: #FEE2E2; }
    .btn-sm.success { background: #ECFDF5; color: #065F46; }
    .btn-sm.success:hover { background: #D1FAE5; }
    
    .security-container {
        max-width: 900px;
        margin: 0 auto;
    }
    
    .security-card {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 28px 32px;
        box-shadow: var(--shadow);
        margin-bottom: 20px;
    }
    .security-card .card-title {
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .security-card .card-title i {
        color: var(--primary);
    }
    .security-card .card-desc {
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
    .form-group .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-top: 6px;
    }
    .form-group .checkbox-group input[type="checkbox"] {
        width: 20px;
        height: 20px;
        accent-color: var(--primary);
        cursor: pointer;
        flex-shrink: 0;
    }
    .form-group .checkbox-group label {
        font-weight: 400;
        cursor: pointer;
        font-size: 0.85rem;
    }
    .form-group .checkbox-group .badge-2fa {
        background: var(--secondary);
        color: white;
        padding: 1px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        margin-left: 6px;
    }
    .form-group .checkbox-group .badge-2fa.disabled {
        background: var(--gray-400);
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
    .form-actions .btn-danger {
        background: var(--danger);
        color: white;
    }
    .form-actions .btn-danger:hover {
        background: #DC2626;
    }
    
    .session-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid var(--gray-100);
    }
    .session-item:last-child {
        border-bottom: none;
    }
    .session-item .session-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .session-item .session-info .device {
        font-weight: 500;
        font-size: 0.85rem;
    }
    .session-item .session-info .details {
        font-size: 0.75rem;
        color: var(--gray-400);
    }
    .session-item .session-status {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .session-item .session-status .badge-active {
        background: #ECFDF5;
        color: #065F46;
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    .session-item .session-status .badge-active i {
        font-size: 6px;
        margin-right: 4px;
    }
    .session-item .session-status .badge-inactive {
        background: var(--gray-100);
        color: var(--gray-500);
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    
    .event-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid var(--gray-100);
    }
    .event-item:last-child {
        border-bottom: none;
    }
    .event-item .event-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        flex-shrink: 0;
    }
    .event-item .event-icon.login { background: #EFF6FF; color: #3B82F6; }
    .event-item .event-icon.security { background: #FEF2F2; color: #EF4444; }
    .event-item .event-icon.password { background: #FFFBEB; color: #F59E0B; }
    .event-item .event-icon.2fa { background: #F5F3FF; color: #8B5CF6; }
    .event-item .event-icon.logout { background: #EFF6FF; color: #3B82F6; }
    .event-item .event-content {
        flex: 1;
    }
    .event-item .event-content .desc {
        font-size: 0.82rem;
    }
    .event-item .event-content .time {
        font-size: 0.7rem;
        color: var(--gray-400);
    }
    .event-item .event-content .ip {
        font-size: 0.7rem;
        color: var(--gray-400);
        font-family: monospace;
    }
    
    .attempt-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid var(--gray-100);
        font-size: 0.82rem;
    }
    .attempt-item:last-child {
        border-bottom: none;
    }
    .attempt-item .attempt-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .attempt-item .attempt-info .email {
        font-weight: 500;
    }
    .attempt-item .attempt-info .ip {
        font-size: 0.7rem;
        color: var(--gray-400);
        font-family: monospace;
    }
    .attempt-item .attempt-status .success {
        color: var(--secondary);
    }
    .attempt-item .attempt-status .failed {
        color: var(--danger);
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
    
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    .badge-status .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .badge-status.enabled { background: #ECFDF5; color: #065F46; }
    .badge-status.enabled .dot { background: #10B981; }
    .badge-status.disabled { background: #FEF2F2; color: #991B1B; }
    .badge-status.disabled .dot { background: #EF4444; }
    
    .empty-state-small {
        text-align: center;
        padding: 20px;
        color: var(--gray-400);
        font-size: 0.85rem;
    }
    .empty-state-small i {
        font-size: 1.6rem;
        display: block;
        margin-bottom: 6px;
        color: var(--gray-300);
    }
    
    @media (max-width: 768px) {
        .security-card {
            padding: 20px;
        }
        .form-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            justify-content: center;
            width: 100%;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .session-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }
        .event-item {
            flex-direction: column;
            align-items: flex-start;
        }
        .attempt-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }
    }
    @media (max-width: 480px) {
        .security-card {
            padding: 16px;
        }
        .form-group input {
            padding: 8px 12px;
            font-size: 0.8rem;
        }
        .session-item .session-status {
            flex-wrap: wrap;
        }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="security-container">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h2>
                        <i class="fas fa-shield-alt" style="color:var(--primary);margin-right:8px;"></i> Security
                        <small>Manage your account security settings</small>
                    </h2>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="profile.php" class="btn-outline">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                    <a href="settings.php" class="btn-outline">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </div>
            </div>

            <!-- Alert Messages -->
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

            <!-- Change Password -->
            <div class="security-card">
                <div class="card-title">
                    <i class="fas fa-key"></i> Change Password
                </div>
                <div class="card-desc">
                    Update your password to keep your account secure.
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Current Password <span class="required">*</span></label>
                            <input type="password" name="current_password" placeholder="Enter current password" required>
                        </div>
                        <div class="form-group">
                            <label>New Password <span class="required">*</span></label>
                            <input type="password" name="new_password" placeholder="Min 8 characters" required>
                            <div class="help-text">Password must be at least 8 characters long.</div>
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password <span class="required">*</span></label>
                            <input type="password" name="confirm_password" placeholder="Re-enter new password" required>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>

            <!-- Two-Factor Authentication -->
            <div class="security-card">
                <div class="card-title">
                    <i class="fas fa-mobile-alt"></i> Two-Factor Authentication
                </div>
                <div class="card-desc">
                    Add an extra layer of security to your account.
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="enable_2fa">
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="two_factor_enabled" id="twoFactorEnabled" value="1" <?php echo ($user['two_factor_enabled'] ?? 0) ? 'checked' : ''; ?> onchange="this.form.submit()">
                            <label for="twoFactorEnabled">
                                Enable Two-Factor Authentication
                                <span class="badge-2fa <?php echo ($user['two_factor_enabled'] ?? 0) ? '' : 'disabled'; ?>">
                                    <?php echo ($user['two_factor_enabled'] ?? 0) ? 'Enabled' : 'Disabled'; ?>
                                </span>
                            </label>
                        </div>
                        <div class="help-text">
                            <?php if ($user['two_factor_enabled'] ?? 0): ?>
                                <i class="fas fa-check-circle" style="color:var(--secondary);"></i> 2FA is currently enabled. You will be prompted for a code on login.
                            <?php else: ?>
                                <i class="fas fa-info-circle"></i> Enable 2FA to receive a verification code on login via email.
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Active Sessions -->
            <div class="security-card">
                <div class="card-title">
                    <i class="fas fa-laptop"></i> Active Sessions
                </div>
                <div class="card-desc">
                    Manage your active sessions across devices.
                </div>
                
                <?php if (count($sessions) > 0): ?>
                    <?php foreach ($sessions as $session): ?>
                        <div class="session-item">
                            <div class="session-info">
                                <div class="device">
                                    <i class="fas fa-<?php echo $session['device_type'] === 'web' ? 'desktop' : ($session['device_type'] === 'android' ? 'android' : 'apple'); ?>"></i>
                                    <?php echo ucfirst($session['device_type']); ?>
                                    <?php if ($session['device_name']): ?>
                                        - <?php echo htmlspecialchars($session['device_name']); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="details">
                                    IP: <?php echo htmlspecialchars($session['ip_address'] ?? 'N/A'); ?>
                                    <span style="margin:0 6px;">·</span>
                                    <?php echo date('M j, Y g:i A', strtotime($session['created_at'])); ?>
                                    <?php if ($session['last_activity_at']): ?>
                                        <span style="margin:0 6px;">·</span>
                                        Last active: <?php echo date('M j, Y g:i A', strtotime($session['last_activity_at'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="session-status">
                                <?php if ($session['is_active']): ?>
                                    <span class="badge-active"><i class="fas fa-circle"></i> Active</span>
                                    <?php if ($session['token'] !== SessionManager::get('session_token')): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="logout_session">
                                            <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                            <button type="submit" class="btn-sm danger" onclick="return confirm('Terminate this session?')">
                                                <i class="fas fa-times"></i> Terminate
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size:0.7rem;color:var(--gray-400);">(Current)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge-inactive">Inactive</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div style="margin-top:16px;">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="logout_all_sessions">
                            <button type="submit" class="btn btn-danger" style="padding:8px 16px;font-size:0.82rem;" onclick="return confirm('This will log you out from all other devices. Continue?')">
                                <i class="fas fa-sign-out-alt"></i> Logout All Other Sessions
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="empty-state-small">
                        <i class="fas fa-laptop"></i>
                        <p>No active sessions found.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Security Events -->
            <div class="security-card">
                <div class="card-title">
                    <i class="fas fa-history"></i> Security Events
                </div>
                <div class="card-desc">
                    Recent security events on your account.
                </div>
                
                <?php if (count($security_events) > 0): ?>
                    <?php foreach (array_slice($security_events, 0, 10) as $event): ?>
                        <?php 
                            $iconClass = 'security';
                            $icon = 'fa-shield-alt';
                            $type = $event['event_type'] ?? '';
                            if (strpos($type, 'login') !== false) {
                                $iconClass = 'login';
                                $icon = 'fa-sign-in-alt';
                            } elseif (strpos($type, 'logout') !== false) {
                                $iconClass = 'logout';
                                $icon = 'fa-sign-out-alt';
                            } elseif (strpos($type, 'password') !== false) {
                                $iconClass = 'password';
                                $icon = 'fa-key';
                            } elseif (strpos($type, '2fa') !== false) {
                                $iconClass = '2fa';
                                $icon = 'fa-mobile-alt';
                            }
                        ?>
                        <div class="event-item">
                            <div class="event-icon <?php echo $iconClass; ?>">
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                            <div class="event-content">
                                <div class="desc"><?php echo htmlspecialchars($event['description'] ?? 'Security event'); ?></div>
                                <div>
                                    <span class="time"><?php echo date('M j, Y g:i A', strtotime($event['created_at'])); ?></span>
                                    <?php if ($event['ip_address']): ?>
                                        <span class="ip">· IP: <?php echo htmlspecialchars($event['ip_address']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state-small">
                        <i class="fas fa-shield-alt"></i>
                        <p>No security events recorded.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Login Attempts -->
            <div class="security-card">
                <div class="card-title">
                    <i class="fas fa-sign-in-alt"></i> Recent Login Attempts
                </div>
                <div class="card-desc">
                    Recent login attempts on your account.
                </div>
                
                <?php if (count($login_attempts) > 0): ?>
                    <?php foreach (array_slice($login_attempts, 0, 10) as $attempt): ?>
                        <div class="attempt-item">
                            <div class="attempt-info">
                                <span class="email"><?php echo htmlspecialchars($attempt['email'] ?? 'N/A'); ?></span>
                                <span class="ip">IP: <?php echo htmlspecialchars($attempt['ip_address'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="attempt-status">
                                <?php if ($attempt['success']): ?>
                                    <span class="success"><i class="fas fa-check-circle"></i> Success</span>
                                <?php else: ?>
                                    <span class="failed"><i class="fas fa-times-circle"></i> Failed</span>
                                <?php endif; ?>
                                <span style="font-size:0.65rem;color:var(--gray-400);margin-left:8px;">
                                    <?php echo date('M j, Y g:i A', strtotime($attempt['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state-small">
                        <i class="fas fa-sign-in-alt"></i>
                        <p>No login attempts recorded.</p>
                    </div>
                <?php endif; ?>
            </div>
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
// SEARCH
// ============================================================
var searchInput = document.getElementById('searchInput');
var searchResults = document.getElementById('searchResults');
var searchTimeout;

if (searchInput) {
    searchInput.addEventListener('input', function() {
        var query = this.value.trim();
        clearTimeout(searchTimeout);
        if (query.length < 2) {
            if (searchResults) searchResults.classList.remove('active');
            return;
        }
        searchTimeout = setTimeout(function() {
            fetch('search.php?q=' + encodeURIComponent(query))
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (searchResults) {
                        searchResults.innerHTML = '';
                        if (data && data.length > 0) {
                            data.forEach(function(item) {
                                var div = document.createElement('a');
                                div.className = 'result-item';
                                div.href = item.url || '#';
                                div.innerHTML = '<i class="fas ' + (item.icon || 'fa-file') + '"></i><span class="text-truncate">' + (item.label || item.name || '') + '</span><span class="result-type">' + ((item.type || '').charAt(0).toUpperCase() + (item.type || '').slice(1)) + '</span>';
                                searchResults.appendChild(div);
                            });
                            searchResults.classList.add('active');
                        } else {
                            searchResults.innerHTML = '<div style="padding:12px;text-align:center;color:var(--gray-500);font-size:0.8rem;"><i class="fas fa-search" style="display:block;font-size:1.2rem;margin-bottom:4px;"></i>No results found</div>';
                            searchResults.classList.add('active');
                        }
                    }
                })
                .catch(function() {});
        }, 300);
    });

    document.addEventListener('click', function(e) {
        var wrapper = document.querySelector('.search-wrapper');
        if (wrapper && !wrapper.contains(e.target) && searchResults) {
            searchResults.classList.remove('active');
        }
    });
}
</script>
</body>
</html>