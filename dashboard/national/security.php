<?php
// ============================================================
// NATIONAL COORDINATOR - SECURITY SETTINGS
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

$db = getDB();

// ============================================================
// FETCH USER DATA
// ============================================================
$user_data = null;
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Security fetch error: " . $e->getMessage());
}

// ============================================================
// FETCH SECURITY EVENTS
// ============================================================
$security_events = [];
$total_events = 0;
try {
    $stmt = $db->prepare("
        SELECT 
            se.*,
            u.full_name as user_name
        FROM security_events se
        LEFT JOIN users u ON se.user_id = u.id
        WHERE se.tenant_id = ? OR se.tenant_id IS NULL
        ORDER BY se.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$tenant_id]);
    $security_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_events = count($security_events);
} catch (Exception $e) {
    error_log("Security events error: " . $e->getMessage());
}

// ============================================================
// FETCH LOGIN HISTORY
// ============================================================
$login_history = [];
try {
    $stmt = $db->prepare("
        SELECT 
            la.*,
            u.full_name as user_name
        FROM login_attempts la
        LEFT JOIN users u ON la.user_id = u.id
        WHERE la.user_id = ? OR la.email = ?
        ORDER BY la.created_at DESC
        LIMIT 30
    ");
    $stmt->execute([$user_id, $user_data['email'] ?? '']);
    $login_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Login history error: " . $e->getMessage());
}

// ============================================================
// FETCH ACTIVE SESSIONS
// ============================================================
$active_sessions = [];
try {
    $stmt = $db->prepare("
        SELECT 
            us.*,
            u.full_name as user_name
        FROM user_sessions us
        LEFT JOIN users u ON us.user_id = u.id
        WHERE us.user_id = ? AND us.is_active = 1
        ORDER BY us.last_activity_at DESC
    ");
    $stmt->execute([$user_id]);
    $active_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Active sessions error: " . $e->getMessage());
}

// ============================================================
// PROCESS 2FA TOGGLE
// ============================================================
$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'toggle_2fa') {
        $enabled = isset($_POST['two_factor_enabled']) ? 1 : 0;
        
        try {
            $stmt = $db->prepare("UPDATE users SET two_factor_enabled = ? WHERE id = ?");
            $stmt->execute([$enabled, $user_id]);
            
            // Log activity
            $activity_type = $enabled ? '2fa_enabled' : '2fa_disabled';
            $log_stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, entity_type, entity_id, created_at)
                VALUES (?, ?, ?, ?, 'user', ?, NOW())
            ");
            $log_stmt->execute([
                $user_id,
                $tenant_id,
                $activity_type,
                $enabled ? "Enabled two-factor authentication" : "Disabled two-factor authentication",
                $user_id
            ]);
            
            $success = true;
            $message = $enabled ? 'Two-factor authentication enabled!' : 'Two-factor authentication disabled!';
            
            // Refresh user data
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $error = 'Failed to update 2FA setting: ' . $e->getMessage();
            error_log("2FA Toggle Error: " . $e->getMessage());
        }
    } elseif ($action === 'revoke_session') {
        $session_id = intval($_POST['session_id'] ?? 0);
        
        try {
            $stmt = $db->prepare("UPDATE user_sessions SET is_active = 0 WHERE id = ? AND user_id = ?");
            $stmt->execute([$session_id, $user_id]);
            
            $success = true;
            $message = 'Session revoked successfully!';
            
            // Refresh sessions
            $stmt = $db->prepare("
                SELECT 
                    us.*,
                    u.full_name as user_name
                FROM user_sessions us
                LEFT JOIN users u ON us.user_id = u.id
                WHERE us.user_id = ? AND us.is_active = 1
                ORDER BY us.last_activity_at DESC
            ");
            $stmt->execute([$user_id]);
            $active_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $error = 'Failed to revoke session: ' . $e->getMessage();
            error_log("Revoke session error: " . $e->getMessage());
        }
    } elseif ($action === 'revoke_all') {
        try {
            $stmt = $db->prepare("
                UPDATE user_sessions 
                SET is_active = 0 
                WHERE user_id = ? AND id != (
                    SELECT id FROM (
                        SELECT id FROM user_sessions 
                        WHERE user_id = ? AND is_active = 1 
                        ORDER BY last_activity_at DESC LIMIT 1
                    ) AS current_session
                )
            ");
            $stmt->execute([$user_id, $user_id]);
            
            $success = true;
            $message = 'All other sessions revoked successfully!';
            
            // Refresh sessions
            $stmt = $db->prepare("
                SELECT 
                    us.*,
                    u.full_name as user_name
                FROM user_sessions us
                LEFT JOIN users u ON us.user_id = u.id
                WHERE us.user_id = ? AND us.is_active = 1
                ORDER BY us.last_activity_at DESC
            ");
            $stmt->execute([$user_id]);
            $active_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $error = 'Failed to revoke sessions: ' . $e->getMessage();
            error_log("Revoke all sessions error: " . $e->getMessage());
        }
    }
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Security';
$page_subtitle = 'Manage your security settings';
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
                <span style="font-weight:600;color:var(--gray-800);">Security</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-shield-alt" style="color:var(--primary);"></i>
                        Security Settings
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-lock"></i> 
                        Manage your account security
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="profile.php" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <a href="settings.php" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-cog"></i> Settings
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

        <!-- Security Grid -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <!-- Two-Factor Authentication -->
            <div style="background:white;border-radius:var(--radius);padding:20px;border:1px solid var(--gray-200);">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0 0 12px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
                    <i class="fas fa-mobile-alt" style="color:var(--primary);margin-right:6px;"></i>
                    Two-Factor Authentication
                </h4>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="toggle_2fa">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                        <div>
                            <p style="font-size:0.85rem;color:var(--gray-600);margin:0;">
                                <?php if ($user_data['two_factor_enabled'] ?? 0): ?>
                                    <span style="color:#10B981;"><i class="fas fa-check-circle"></i> Enabled</span>
                                    <span style="color:var(--gray-400);margin-left:8px;">Your account is protected with 2FA</span>
                                <?php else: ?>
                                    <span style="color:#EF4444;"><i class="fas fa-times-circle"></i> Disabled</span>
                                    <span style="color:var(--gray-400);margin-left:8px;">Your account is not protected with 2FA</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" name="two_factor_enabled" value="1" 
                                   <?php echo ($user_data['two_factor_enabled'] ?? 0) ? 'checked' : ''; ?> 
                                   onchange="this.form.submit()">
                            <span style="font-size:0.8rem;font-weight:500;color:var(--gray-700);">
                                <?php echo ($user_data['two_factor_enabled'] ?? 0) ? 'Disable' : 'Enable'; ?>
                            </span>
                        </label>
                    </div>
                </form>
                <?php if ($user_data['two_factor_enabled'] ?? 0): ?>
                    <div style="margin-top:12px;padding:12px;background:#F0FDF4;border-radius:8px;border:1px solid #A7F3D0;">
                        <p style="font-size:0.75rem;color:#065F46;margin:0;">
                            <i class="fas fa-info-circle"></i> 
                            Two-factor authentication adds an extra layer of security to your account.
                            You will be prompted for a verification code each time you log in.
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Active Sessions -->
            <div style="background:white;border-radius:var(--radius);padding:20px;border:1px solid var(--gray-200);">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0 0 12px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
                    <i class="fas fa-desktop" style="color:var(--secondary);margin-right:6px;"></i>
                    Active Sessions
                    <span style="font-size:0.7rem;font-weight:400;color:var(--gray-400);margin-left:8px;">
                        (<?php echo count($active_sessions); ?> active)
                    </span>
                </h4>
                <?php if (count($active_sessions) > 0): ?>
                    <div style="max-height:200px;overflow-y:auto;">
                        <?php foreach ($active_sessions as $session): ?>
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--gray-100);">
                                <div>
                                    <div style="font-weight:500;font-size:0.8rem;">
                                        <i class="fas fa-<?php echo $session['device_type'] === 'web' ? 'laptop' : ($session['device_type'] === 'android' ? 'android' : 'apple'); ?>"></i>
                                        <?php echo ucfirst($session['device_type'] ?? 'Web'); ?>
                                    </div>
                                    <div style="font-size:0.65rem;color:var(--gray-400);">
                                        <?php echo htmlspecialchars($session['ip_address'] ?? 'Unknown IP'); ?>
                                        <?php if (!empty($session['device_name'])): ?>
                                            • <?php echo htmlspecialchars($session['device_name']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size:0.6rem;color:var(--gray-400);">
                                        Last active: <?php echo date('M j, Y g:i A', strtotime($session['last_activity_at'])); ?>
                                    </div>
                                </div>
                                <form method="POST" action="" style="margin:0;">
                                    <input type="hidden" name="action" value="revoke_session">
                                    <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                    <button type="submit" class="btn-sm" style="padding:2px 10px;border-radius:4px;background:#FEE2E2;color:#991B1B;border:none;cursor:pointer;font-size:0.65rem;transition:var(--transition);" 
                                            onclick="return confirm('Revoke this session?')">
                                        <i class="fas fa-times"></i> Revoke
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--gray-200);">
                        <form method="POST" action="" style="margin:0;">
                            <input type="hidden" name="action" value="revoke_all">
                            <button type="submit" class="btn-sm" style="padding:4px 16px;border-radius:6px;background:#EF4444;color:white;border:none;cursor:pointer;font-size:0.7rem;transition:var(--transition);" 
                                    onclick="return confirm('Revoke all other sessions? You will be logged out from all other devices.')">
                                <i class="fas fa-power-off"></i> Revoke All Others
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <p style="color:var(--gray-400);text-align:center;padding:16px 0;">No active sessions</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Security Events -->
        <div style="background:white;border-radius:var(--radius);padding:20px;border:1px solid var(--gray-200);margin-top:20px;">
            <h4 style="font-size:0.9rem;font-weight:600;margin:0 0 12px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
                <i class="fas fa-history" style="color:var(--warning);margin-right:6px;"></i>
                Security Events
                <span style="font-size:0.7rem;font-weight:400;color:var(--gray-400);margin-left:8px;">
                    (<?php echo number_format($total_events); ?> events)
                </span>
            </h4>
            <?php if (count($security_events) > 0): ?>
                <div style="max-height:300px;overflow-y:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.75rem;">
                        <thead style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
                            <tr>
                                <th style="padding:6px 10px;text-align:left;font-weight:600;color:var(--gray-600);">Event</th>
                                <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">User</th>
                                <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">IP</th>
                                <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">Risk</th>
                                <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($security_events, 0, 20) as $event): ?>
                                <tr style="border-bottom:1px solid var(--gray-100);">
                                    <td style="padding:6px 10px;">
                                        <span style="font-weight:500;"><?php echo ucfirst(str_replace('_', ' ', $event['event_type'] ?? 'Unknown')); ?></span>
                                        <div style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars(substr($event['description'] ?? '', 0, 50)) . (strlen($event['description'] ?? '') > 50 ? '...' : ''); ?></div>
                                    </td>
                                    <td style="padding:6px 10px;text-align:center;font-size:0.7rem;">
                                        <?php echo htmlspecialchars($event['user_name'] ?? 'System'); ?>
                                    </td>
                                    <td style="padding:6px 10px;text-align:center;font-size:0.7rem;color:var(--gray-500);">
                                        <?php echo htmlspecialchars($event['ip_address'] ?? 'N/A'); ?>
                                    </td>
                                    <td style="padding:6px 10px;text-align:center;">
                                        <?php if (!empty($event['risk_score'])): ?>
                                            <span style="display:inline-block;padding:1px 8px;border-radius:8px;font-size:0.6rem;font-weight:600;background:<?php echo $event['risk_score'] > 7 ? '#FEE2E2' : ($event['risk_score'] > 4 ? '#FEF3C7' : '#D1FAE5'); ?>;color:<?php echo $event['risk_score'] > 7 ? '#991B1B' : ($event['risk_score'] > 4 ? '#92400E' : '#065F46'); ?>;">
                                                <?php echo $event['risk_score']; ?>/10
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);font-size:0.6rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:6px 10px;text-align:center;font-size:0.65rem;color:var(--gray-500);">
                                        <?php echo date('M j, Y g:i A', strtotime($event['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color:var(--gray-400);text-align:center;padding:16px 0;">No security events recorded</p>
            <?php endif; ?>
        </div>

        <!-- Login History -->
        <div style="background:white;border-radius:var(--radius);padding:20px;border:1px solid var(--gray-200);margin-top:20px;">
            <h4 style="font-size:0.9rem;font-weight:600;margin:0 0 12px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
                <i class="fas fa-sign-in-alt" style="color:var(--primary);margin-right:6px;"></i>
                Login History
                <span style="font-size:0.7rem;font-weight:400;color:var(--gray-400);margin-left:8px;">
                    (<?php echo count($login_history); ?> attempts)
                </span>
            </h4>
            <?php if (count($login_history) > 0): ?>
                <div style="max-height:250px;overflow-y:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.75rem;">
                        <thead style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
                            <tr>
                                <th style="padding:6px 10px;text-align:left;font-weight:600;color:var(--gray-600);">User</th>
                                <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">IP Address</th>
                                <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">Status</th>
                                <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($login_history as $attempt): ?>
                                <tr style="border-bottom:1px solid var(--gray-100);">
                                    <td style="padding:6px 10px;">
                                        <?php echo htmlspecialchars($attempt['user_name'] ?? ($attempt['email'] ?? 'Unknown')); ?>
                                    </td>
                                    <td style="padding:6px 10px;text-align:center;font-size:0.7rem;color:var(--gray-500);">
                                        <?php echo htmlspecialchars($attempt['ip_address'] ?? 'N/A'); ?>
                                    </td>
                                    <td style="padding:6px 10px;text-align:center;">
                                        <?php if ($attempt['success'] ?? 0): ?>
                                            <span style="display:inline-block;padding:1px 8px;border-radius:8px;font-size:0.6rem;font-weight:600;background:#D1FAE5;color:#065F46;">
                                                <i class="fas fa-check"></i> Success
                                            </span>
                                        <?php else: ?>
                                            <span style="display:inline-block;padding:1px 8px;border-radius:8px;font-size:0.6rem;font-weight:600;background:#FEE2E2;color:#991B1B;">
                                                <i class="fas fa-times"></i> Failed
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:6px 10px;text-align:center;font-size:0.65rem;color:var(--gray-500);">
                                        <?php echo date('M j, Y g:i A', strtotime($attempt['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color:var(--gray-400);text-align:center;padding:16px 0;">No login history found</p>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
.btn-sm:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.btn-secondary:hover {
    background: var(--gray-200);
    transform: translateY(-1px);
}

@media (max-width: 768px) {
    div[style*="grid-template-columns:1fr 1fr;gap:20px;"] {
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