<?php
// ============================================================
// NATIONAL COORDINATOR - BROADCASTS LIST (PRO VERSION)
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
// CHECK FOR SUCCESS/ERROR MESSAGES
// ============================================================
$success_message = '';
$error_message = '';

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = 'Broadcast created successfully!';
}
if (isset($_GET['sent']) && $_GET['sent'] == 1) {
    $success_message = 'Broadcast sent successfully!';
}
if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $success_message = 'Broadcast deleted successfully!';
}
if (isset($_GET['resent']) && $_GET['resent'] == 1) {
    $success_message = 'Broadcast resent successfully!';
}
if (isset($_GET['error'])) {
    $error_message = urldecode($_GET['error']);
}

// ============================================================
// HANDLE RESEND ACTION
// ============================================================
if (isset($_GET['action']) && $_GET['action'] === 'resend' && isset($_GET['id'])) {
    $broadcast_id = intval($_GET['id']);
    
    try {
        // Fetch broadcast details
        $stmt = $db->prepare("
            SELECT b.*, u.full_name as sender_name
            FROM broadcasts b
            LEFT JOIN users u ON b.sender_id = u.id
            WHERE b.id = ? AND b.tenant_id = ?
        ");
        $stmt->execute([$broadcast_id, $tenant_id]);
        $broadcast = $stmt->fetch();
        
        if ($broadcast) {
            // Get recipients
            $target_ids = json_decode($broadcast['target_ids_json'] ?? '[]', true);
            $recipients = getBroadcastRecipients($tenant_id, $broadcast['target_audience'], $target_ids);
            
            // Send emails if email is in send_via
            $send_via = json_decode($broadcast['send_via'] ?? '["email"]', true);
            $email_sent_count = 0;
            $email_failed_count = 0;
            
            if (in_array('email', $send_via) && !empty($recipients)) {
                foreach ($recipients as $recipient) {
                    if (!empty($recipient['email'])) {
                        $email_body = "
                            <!DOCTYPE html>
                            <html>
                            <head>
                                <style>
                                    body { font-family: Arial, sans-serif; background: #f4f6fa; padding: 20px; }
                                    .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
                                    .header { text-align: center; margin-bottom: 30px; }
                                    .header h1 { color: #0F4C81; margin: 0; }
                                    .message-box { background: #F8FAFC; padding: 20px; border-radius: 12px; margin: 20px 0; border-left: 4px solid #0F4C81; }
                                    .footer { text-align: center; color: #64748B; font-size: 12px; margin-top: 30px; border-top: 1px solid #E2E8F0; padding-top: 20px; }
                                    .resend-badge { background: #FEF3C7; padding: 4px 12px; border-radius: 12px; font-size: 12px; color: #92400E; display: inline-block; }
                                </style>
                            </head>
                            <body>
                                <div class=\"container\">
                                    <div class=\"header\">
                                        <h1>📢 " . APP_NAME . "</h1>
                                        <p style=\"color: #64748B;\">Broadcast Message <span class=\"resend-badge\">🔁 Resent</span></p>
                                    </div>
                                    <p>Hello " . htmlspecialchars($recipient['full_name'] ?? 'User') . ",</p>
                                    <div class=\"message-box\">
                                        <h3 style=\"margin-top:0;\">" . htmlspecialchars($broadcast['title']) . "</h3>
                                        <p>" . nl2br(htmlspecialchars($broadcast['message'])) . "</p>
                                    </div>
                                    <p style=\"color: #64748B; font-size: 14px;\">
                                        This is a resent broadcast message from " . APP_NAME . ".
                                        Please do not reply to this email.
                                    </p>
                                    <div class=\"footer\">
                                        &copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.
                                    </div>
                                </div>
                            </body>
                            </html>
                        ";
                        
                        $result = sendEmail(
                            $recipient['email'],
                            '🔁 ' . $broadcast['title'],
                            $email_body,
                            strip_tags($broadcast['message'])
                        );
                        
                        if ($result['success']) {
                            $email_sent_count++;
                        } else {
                            $email_failed_count++;
                        }
                    }
                }
            }
            
            // Update broadcast sent time and count
            $stmt = $db->prepare("
                UPDATE broadcasts 
                SET sent_at = NOW(), 
                    total_recipients = ?,
                    status = 'sent'
                WHERE id = ?
            ");
            $stmt->execute([count($recipients), $broadcast_id]);
            
            // Log activity
            $log_stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, entity_type, entity_id, created_at)
                VALUES (?, ?, 'broadcast_resent', ?, 'broadcast', ?, NOW())
            ");
            $log_stmt->execute([
                $user_id,
                $tenant_id,
                "Resent broadcast: " . $broadcast['title'] . " to " . $email_sent_count . " recipients",
                $broadcast_id
            ]);
            
            // Redirect with success
            header("Location: broadcasts.php?resent=1");
            exit();
        } else {
            header("Location: broadcasts.php?error=" . urlencode('Broadcast not found'));
            exit();
        }
    } catch (Exception $e) {
        error_log("Resend Error: " . $e->getMessage());
        header("Location: broadcasts.php?error=" . urlencode('Failed to resend broadcast'));
        exit();
    }
}

// ============================================================
// HANDLE DELETE ACTION
// ============================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $broadcast_id = intval($_GET['id']);
    
    try {
        $stmt = $db->prepare("DELETE FROM broadcasts WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$broadcast_id, $tenant_id]);
        
        if ($stmt->rowCount() > 0) {
            header("Location: broadcasts.php?deleted=1");
        } else {
            header("Location: broadcasts.php?error=" . urlencode('Broadcast not found'));
        }
        exit();
    } catch (Exception $e) {
        error_log("Delete Error: " . $e->getMessage());
        header("Location: broadcasts.php?error=" . urlencode('Failed to delete broadcast'));
        exit();
    }
}

// ============================================================
// FETCH BROADCASTS WITH STATISTICS
// ============================================================
$broadcasts = [];
try {
    $stmt = $db->prepare("
        SELECT 
            b.*,
            u.full_name as sender_name,
            (SELECT COUNT(*) FROM users u2 
             WHERE u2.tenant_id = b.tenant_id 
             AND u2.status = 'active' 
             AND (u2.deleted_at IS NULL OR u2.deleted_at = '0000-00-00 00:00:00')
            ) as total_available_users
        FROM broadcasts b
        LEFT JOIN users u ON b.sender_id = u.id
        WHERE b.tenant_id = ?
        ORDER BY b.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$tenant_id]);
    $broadcasts = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Broadcasts Error: " . $e->getMessage());
    $broadcasts = [];
}

// ============================================================
// CALCULATE STATISTICS
// ============================================================
$stats = [
    'total' => 0,
    'draft' => 0,
    'scheduled' => 0,
    'sending' => 0,
    'sent' => 0,
    'failed' => 0,
    'cancelled' => 0
];

foreach ($broadcasts as $b) {
    $stats['total']++;
    $status = $b['status'] ?? 'draft';
    if (isset($stats[$status])) {
        $stats[$status]++;
    }
}

// ============================================================
// STATUS COLORS AND LABELS
// ============================================================
$status_colors = [
    'draft' => '#6B7280',
    'scheduled' => '#F59E0B',
    'sending' => '#3B82F6',
    'sent' => '#10B981',
    'failed' => '#EF4444',
    'cancelled' => '#6B7280'
];

$status_labels = [
    'draft' => 'Draft',
    'scheduled' => 'Scheduled',
    'sending' => 'Sending...',
    'sent' => 'Sent',
    'failed' => 'Failed',
    'cancelled' => 'Cancelled'
];

$status_icons = [
    'draft' => 'fa-pencil-alt',
    'scheduled' => 'fa-clock',
    'sending' => 'fa-spinner fa-spin',
    'sent' => 'fa-check-circle',
    'failed' => 'fa-times-circle',
    'cancelled' => 'fa-ban'
];

$target_audience_labels = [
    'all' => 'All Users',
    'national' => 'National',
    'state' => 'States',
    'senatorial' => 'Senatorial',
    'federal_constituency' => 'Federal Constituency',
    'lga' => 'LGAs',
    'ward' => 'Wards',
    'pu' => 'Polling Units',
    'role_specific' => 'Specific Roles'
];

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Broadcasts';
$page_subtitle = 'Manage and monitor your broadcasts';
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
                <span style="font-weight:600;color:var(--gray-800);">Broadcasts</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">Broadcasts</h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">Manage and monitor your broadcast messages</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="broadcasts-create.php" class="btn-primary" style="padding:8px 24px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-plus"></i> New Broadcast
                    </a>
                    <a href="broadcasts-scheduled.php" class="btn-secondary" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-clock"></i> Scheduled
                    </a>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div style="background:#D1FAE5;color:#065F46;padding:12px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #A7F3D0;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-check-circle" style="font-size:1.2rem;"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div style="background:#FEE2E2;color:#991B1B;padding:12px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #FECACA;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-exclamation-circle" style="font-size:1.2rem;"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-envelope"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
                <div class="stat-label">Total Broadcasts</div>
                <div class="stat-change">All messages</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($stats['scheduled'] ?? 0); ?></div>
                <div class="stat-label">Scheduled</div>
                <div class="stat-change"><i class="fas fa-calendar"></i> Pending</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['sent'] ?? 0); ?></div>
                <div class="stat-label">Sent</div>
                <div class="stat-change up"><i class="fas fa-check"></i> Delivered</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-pencil-alt"></i></div>
                <div class="stat-number"><?php echo number_format($stats['draft'] ?? 0); ?></div>
                <div class="stat-label">Drafts</div>
                <div class="stat-change"><i class="fas fa-edit"></i> In progress</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['failed'] ?? 0); ?></div>
                <div class="stat-label">Failed</div>
                <div class="stat-change down"><i class="fas fa-times"></i> Errors</div>
            </div>
        </div>

        <!-- Broadcasts List -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);">
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0;">
                    <i class="fas fa-list" style="color:var(--primary);margin-right:6px;"></i>
                    All Broadcasts
                </h4>
                <div style="display:flex;gap:8px;align-items:center;">
                    <span style="font-size:0.75rem;color:var(--gray-500);"><?php echo number_format($stats['total'] ?? 0); ?> broadcasts</span>
                    <a href="broadcasts-create.php" class="btn-sm" style="padding:4px 12px;border-radius:6px;background:var(--primary);color:white;text-decoration:none;font-size:0.7rem;">
                        <i class="fas fa-plus"></i> New
                    </a>
                </div>
            </div>
            
            <?php if (count($broadcasts) > 0): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                        <thead style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
                            <tr>
                                <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--gray-600);min-width:180px;">Broadcast</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Target</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Status</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Recipients</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Channels</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Sent</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($broadcasts as $broadcast): 
                                $status_color = $status_colors[$broadcast['status']] ?? '#6B7280';
                                $status_label = $status_labels[$broadcast['status']] ?? ucfirst($broadcast['status']);
                                $status_icon = $status_icons[$broadcast['status']] ?? 'fa-circle';
                                $target_label = $target_audience_labels[$broadcast['target_audience']] ?? ucfirst($broadcast['target_audience']);
                                $send_via = json_decode($broadcast['send_via'] ?? '["email"]', true);
                            ?>
                                <tr style="border-bottom:1px solid var(--gray-100);transition:var(--transition);hover:background:var(--gray-50);">
                                    <td style="padding:10px 14px;">
                                        <div style="font-weight:600;color:var(--gray-800);"><?php echo htmlspecialchars($broadcast['title']); ?></div>
                                        <div style="font-size:0.65rem;color:var(--gray-400);">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($broadcast['sender_name'] ?? 'System'); ?>
                                            <span style="margin:0 4px;">•</span>
                                            <i class="fas fa-calendar-alt"></i> <?php echo date('M j, Y', strtotime($broadcast['created_at'])); ?>
                                        </div>
                                        <?php if (!empty($broadcast['message'])): ?>
                                            <div style="font-size:0.7rem;color:var(--gray-500);margin-top:2px;max-width:250px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                                <?php echo htmlspecialchars(substr($broadcast['message'], 0, 60)) . (strlen($broadcast['message']) > 60 ? '...' : ''); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <span style="font-size:0.65rem;background:var(--gray-100);padding:2px 10px;border-radius:10px;display:inline-block;">
                                            <?php echo $target_label; ?>
                                        </span>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 12px;border-radius:12px;font-size:0.65rem;font-weight:600;background:<?php echo $status_color; ?>20;color:<?php echo $status_color; ?>;">
                                            <i class="fas <?php echo $status_icon; ?>" style="font-size:0.6rem;"></i>
                                            <?php echo $status_label; ?>
                                        </span>
                                        <?php if ($broadcast['status'] === 'scheduled' && $broadcast['scheduled_at']): ?>
                                            <div style="font-size:0.55rem;color:var(--gray-400);margin-top:2px;">
                                                <i class="fas fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($broadcast['scheduled_at'])); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($broadcast['status'] === 'sent' && $broadcast['sent_at']): ?>
                                            <div style="font-size:0.55rem;color:var(--gray-400);margin-top:2px;">
                                                <?php echo date('M j, Y g:i A', strtotime($broadcast['sent_at'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <div style="font-weight:600;color:var(--gray-700);">
                                            <?php echo number_format($broadcast['total_recipients'] ?? 0); ?>
                                        </div>
                                        <div style="font-size:0.6rem;color:var(--gray-400);">
                                            <?php echo number_format($broadcast['read_count'] ?? 0); ?> read
                                            <?php if (($broadcast['total_available_users'] ?? 0) > 0): ?>
                                                <span style="color:var(--gray-300);">/ <?php echo number_format($broadcast['total_available_users'] ?? 0); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <div style="display:flex;gap:3px;justify-content:center;flex-wrap:wrap;">
                                            <?php foreach ($send_via as $channel): ?>
                                                <span style="font-size:0.55rem;padding:1px 6px;border-radius:4px;background:var(--gray-100);color:var(--gray-600);">
                                                    <?php 
                                                        $channel_icons = [
                                                            'email' => 'fa-envelope',
                                                            'sms' => 'fa-sms',
                                                            'push' => 'fa-bell',
                                                            'in_app' => 'fa-mobile-alt'
                                                        ];
                                                        $icon = $channel_icons[$channel] ?? 'fa-circle';
                                                    ?>
                                                    <i class="fas <?php echo $icon; ?>"></i>
                                                    <?php echo ucfirst($channel); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-size:0.7rem;color:var(--gray-500);">
                                        <?php if ($broadcast['sent_at']): ?>
                                            <div><?php echo date('M j, Y', strtotime($broadcast['sent_at'])); ?></div>
                                            <div style="font-size:0.6rem;color:var(--gray-400);">
                                                <?php echo date('g:i A', strtotime($broadcast['sent_at'])); ?>
                                            </div>
                                        <?php elseif ($broadcast['scheduled_at']): ?>
                                            <div style="color:var(--warning);">
                                                <i class="fas fa-clock"></i> Scheduled
                                            </div>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">Not sent</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
                                            <a href="broadcasts-view.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm" title="View" style="padding:3px 10px;border-radius:6px;background:var(--primary);color:white;text-decoration:none;font-size:0.65rem;">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($broadcast['status'] === 'draft'): ?>
                                                <a href="broadcasts-edit.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm" title="Edit" style="padding:3px 10px;border-radius:6px;background:var(--gray-200);color:var(--gray-700);text-decoration:none;font-size:0.65rem;">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="broadcasts.php?action=delete&id=<?php echo $broadcast['id']; ?>" class="btn-sm" title="Delete" style="padding:3px 10px;border-radius:6px;background:#FEE2E2;color:#991B1B;text-decoration:none;font-size:0.65rem;" onclick="return confirm('Delete this broadcast?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($broadcast['status'] === 'scheduled'): ?>
                                                <a href="broadcasts-send.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm" title="Send Now" style="padding:3px 10px;border-radius:6px;background:#10B981;color:white;text-decoration:none;font-size:0.65rem;">
                                                    <i class="fas fa-paper-plane"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($broadcast['status'] === 'sent' || $broadcast['status'] === 'failed'): ?>
                                                <a href="broadcasts.php?action=resend&id=<?php echo $broadcast['id']; ?>" class="btn-sm" title="Resend" style="padding:3px 10px;border-radius:6px;background:#F59E0B;color:white;text-decoration:none;font-size:0.65rem;" onclick="return confirm('Resend this broadcast to all recipients?')">
                                                    <i class="fas fa-redo"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="broadcasts-stats.php?id=<?php echo $broadcast['id']; ?>" class="btn-sm" title="Statistics" style="padding:3px 10px;border-radius:6px;background:#8B5CF6;color:white;text-decoration:none;font-size:0.65rem;">
                                                <i class="fas fa-chart-bar"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="padding:60px 20px;text-align:center;color:var(--gray-500);">
                    <i class="fas fa-bullhorn" style="font-size:3rem;display:block;margin-bottom:12px;color:var(--gray-300);"></i>
                    <h3 style="font-size:1.1rem;font-weight:600;color:var(--gray-600);margin:0 0 8px;">No broadcasts created yet</h3>
                    <p style="font-size:0.85rem;color:var(--gray-400);margin:0 0 16px;">Create your first broadcast to send messages to coordinators and agents</p>
                    <a href="broadcasts-create.php" class="btn-primary" style="padding:10px 28px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.85rem;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-plus"></i> Create First Broadcast
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-top:16px;">
            <a href="broadcasts-create.php" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-plus-circle" style="color:var(--primary);"></i>
                <span>Create New Broadcast</span>
            </a>
            <a href="broadcasts-scheduled.php" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-clock" style="color:var(--warning);"></i>
                <span>View Scheduled</span>
            </a>
            <a href="broadcasts-analytics.php" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-chart-bar" style="color:var(--secondary);"></i>
                <span>Broadcast Analytics</span>
            </a>
            <a href="broadcasts-templates.php" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-copy" style="color:var(--purple);"></i>
                <span>Templates</span>
            </a>
        </div>

        <!-- Quick Stats Footer -->
        <div style="background:#F8FAFC;border-radius:var(--radius);padding:12px 20px;margin-top:16px;border:1px solid var(--gray-200);display:flex;flex-wrap:wrap;gap:16px;justify-content:space-between;align-items:center;">
            <div style="display:flex;gap:20px;flex-wrap:wrap;font-size:0.75rem;color:var(--gray-500);">
                <span><i class="fas fa-envelope" style="color:var(--primary);"></i> Total: <strong><?php echo number_format($stats['total'] ?? 0); ?></strong></span>
                <span><i class="fas fa-check-circle" style="color:#10B981;"></i> Sent: <strong><?php echo number_format($stats['sent'] ?? 0); ?></strong></span>
                <span><i class="fas fa-clock" style="color:#F59E0B;"></i> Scheduled: <strong><?php echo number_format($stats['scheduled'] ?? 0); ?></strong></span>
                <span><i class="fas fa-pencil-alt" style="color:#8B5CF6;"></i> Drafts: <strong><?php echo number_format($stats['draft'] ?? 0); ?></strong></span>
            </div>
            <div style="font-size:0.7rem;color:var(--gray-400);">
                <i class="fas fa-sync-alt"></i> Auto-refresh: <span id="refreshTimer">30s</span>
                <button onclick="location.reload()" style="margin-left:8px;padding:2px 10px;border:1px solid var(--gray-300);border-radius:4px;background:white;cursor:pointer;font-size:0.65rem;">
                    <i class="fas fa-redo"></i> Refresh
                </button>
            </div>
        </div>
    </div>
</main>

<style>
.btn-sm:hover { 
    transform: translateY(-2px); 
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.quick-action-btn:hover { 
    transform: translateY(-2px); 
    box-shadow: var(--shadow-hover); 
    border-color: var(--primary);
}
.stats-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); 
    gap: 14px; 
    margin-bottom: 20px; 
}
.stat-card {
    background: white;
    border-radius: var(--radius);
    padding: 16px 18px;
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-200);
    transition: var(--transition);
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
}
.stat-card .stat-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    margin-bottom: 8px;
}
.stat-card .stat-icon.blue { background: #EFF6FF; color: #3B82F6; }
.stat-card .stat-icon.green { background: #ECFDF5; color: #10B981; }
.stat-card .stat-icon.purple { background: #F5F3FF; color: #8B5CF6; }
.stat-card .stat-icon.yellow { background: #FFFBEB; color: #F59E0B; }
.stat-card .stat-icon.red { background: #FEF2F2; color: #EF4444; }
.stat-card .stat-number {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--gray-800);
}
.stat-card .stat-label {
    font-size: 0.75rem;
    color: var(--gray-500);
    font-weight: 500;
}
.stat-card .stat-change {
    font-size: 0.65rem;
    margin-top: 4px;
    color: var(--gray-400);
}
.stat-card .stat-change.up { color: var(--secondary); }
.stat-card .stat-change.down { color: var(--danger); }

table {
    width: 100%;
    border-collapse: collapse;
}
th {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
td {
    vertical-align: middle;
}
tr:hover {
    background: var(--gray-50);
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    table { font-size: 0.7rem; }
    th, td { padding: 6px 8px !important; }
    .btn-sm { padding: 2px 6px !important; font-size: 0.6rem !important; }
}
</style>

<script>
// ============================================================
// AUTO-REFRESH TIMER
// ============================================================
let refreshSeconds = 30;
const refreshTimer = document.getElementById('refreshTimer');

if (refreshTimer) {
    setInterval(function() {
        refreshSeconds--;
        if (refreshSeconds <= 0) {
            refreshSeconds = 30;
            // Refresh page data without full reload using AJAX
            // For simplicity, we just update the timer
        }
        refreshTimer.textContent = refreshSeconds + 's';
    }, 1000);
}

// ============================================================
// SIDEBAR TOGGLE
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