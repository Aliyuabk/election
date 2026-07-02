<?php
// ============================================================
// DEVICE VERIFICATION - Manage trusted devices
// ============================================================
require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

SessionManager::start();

// Check if logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = SessionManager::get('user_id');
$db = getDB();
$error = '';
$success = '';

// Get all devices
$devices = getTrustedDevices($user_id);
$current_device = generateDeviceFingerprint();

// Handle device actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $device_id = $_POST['device_id'] ?? '';
    
    if ($action === 'revoke' && $device_id) {
        try {
            $stmt = $db->prepare("UPDATE user_sessions SET is_active = 0 WHERE id = ? AND user_id = ?");
            $stmt->execute([$device_id, $user_id]);
            $success = 'Device has been revoked successfully.';
            
            // Refresh devices list
            $devices = getTrustedDevices($user_id);
        } catch (Exception $e) {
            $error = 'Failed to revoke device.';
        }
    } elseif ($action === 'revoke_all') {
        try {
            $stmt = $db->prepare("UPDATE user_sessions SET is_active = 0 WHERE user_id = ? AND token != ?");
            $stmt->execute([$user_id, SessionManager::get('session_token')]);
            $success = 'All other devices have been revoked.';
            
            // Refresh devices list
            $devices = getTrustedDevices($user_id);
        } catch (Exception $e) {
            $error = 'Failed to revoke devices.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Device Verification - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&family=Poppins:wght@600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #F8FAFC;
            color: #0F172A;
            line-height: 1.6;
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; border-radius: 32px; padding: 48px 40px; box-shadow: 0 20px 60px rgba(15, 76, 129, 0.08); border: 1px solid #E2E8F0; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
        .header h1 { font-size: 1.8rem; color: #0F4C81; }
        .btn-primary { padding: 12px 24px; border: none; border-radius: 12px; background: #0F4C81; color: white; font-size: 0.9rem; font-weight: 600; font-family: 'Inter', sans-serif; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary:hover { background: #1a3f6a; transform: translateY(-1px); }
        .btn-danger { background: #DC2626; }
        .btn-danger:hover { background: #B91C1C; }
        .btn-secondary { background: #F1F5F9; color: #0F172A; }
        .btn-secondary:hover { background: #E2E8F0; }
        .device-list { margin: 20px 0; }
        .device-item { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; background: #F8FAFC; border-radius: 12px; margin-bottom: 12px; border: 1px solid #E2E8F0; }
        .device-info { display: flex; align-items: center; gap: 16px; }
        .device-info i { font-size: 1.5rem; color: #0F4C81; }
        .device-details { flex: 1; }
        .device-details .name { font-weight: 600; }
        .device-details .meta { font-size: 0.85rem; color: #64748B; }
        .device-status { font-size: 0.8rem; font-weight: 600; padding: 2px 12px; border-radius: 30px; }
        .device-status.current { background: #ECFDF5; color: #065F46; }
        .device-status.trusted { background: #DBEAFE; color: #1A3F6A; }
        .device-status.unknown { background: #FEF2F2; color: #DC2626; }
        .error-message { background: #FEF2F2; color: #DC2626; padding: 12px 16px; border-radius: 12px; font-size: 0.9rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; border: 1px solid #FECACA; }
        .success-message { background: #ECFDF5; color: #065F46; padding: 12px 16px; border-radius: 12px; font-size: 0.9rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; border: 1px solid #A7F3D0; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #64748B; text-decoration: none; font-size: 0.9rem; transition: 0.15s; display: inline-flex; align-items: center; gap: 8px; }
        .back-link a:hover { color: #0F4C81; }
        .empty-state { text-align: center; padding: 40px 20px; color: #64748B; }
        .empty-state i { font-size: 3rem; color: #94A3B8; margin-bottom: 16px; }
        @media (max-width: 480px) { .card { padding: 32px 24px; } .device-item { flex-direction: column; gap: 12px; align-items: flex-start; } .device-status { align-self: flex-start; } .header { flex-direction: column; gap: 12px; align-items: flex-start; } }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="header">
            <h1><i class="fas fa-laptop"></i> Trusted Devices</h1>
            <div>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="revoke_all" />
                    <button type="submit" class="btn-primary btn-danger" onclick="return confirm('Revoke all other devices?');">
                        <i class="fas fa-sign-out-alt"></i> Revoke All Others
                    </button>
                </form>
            </div>
        </div>
        
        <?php if (!empty($error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>
        
        <div class="device-list">
            <?php if (empty($devices)): ?>
            <div class="empty-state">
                <i class="fas fa-laptop"></i>
                <p>No trusted devices found.</p>
                <p style="font-size: 0.9rem;">Devices will appear here when you log in from them.</p>
            </div>
            <?php else: ?>
                <?php foreach ($devices as $device): ?>
                <div class="device-item">
                    <div class="device-info">
                        <i class="fas fa-<?php echo $device['device_type'] === 'web' ? 'desktop' : 'mobile-alt'; ?>"></i>
                        <div class="device-details">
                            <div class="name">
                                <?php echo htmlspecialchars($device['device_name'] ?? $device['device_type'] ?? 'Unknown Device'); ?>
                                <?php if ($device['device_id'] === $current_device || $device['token'] === SessionManager::get('session_token')): ?>
                                    <span class="device-status current">Current</span>
                                <?php endif; ?>
                            </div>
                            <div class="meta">
                                <i class="fas fa-map-marker-alt"></i> IP: <?php echo htmlspecialchars($device['ip_address'] ?? 'Unknown'); ?>
                                <span style="margin: 0 8px;">•</span>
                                <i class="fas fa-clock"></i> Last active: <?php echo date('M d, Y H:i', strtotime($device['last_activity_at'] ?? $device['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($device['token'] !== SessionManager::get('session_token')): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="revoke" />
                        <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>" />
                        <button type="submit" class="btn-primary btn-danger" style="padding: 6px 16px; font-size: 0.8rem;" onclick="return confirm('Revoke this device?');">
                            <i class="fas fa-times"></i> Revoke
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="back-link">
            <a href="../dashboard/index.php">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>
</body>
</html>