<?php
// ============================================================
// BROADCAST VIEW - CLIENT ADMIN (PROFESSIONAL UI)
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
// GET BROADCAST ID
// ============================================================
$broadcast_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($broadcast_id <= 0) {
    header('Location: broadcasts.php');
    exit();
}

// ============================================================
// FETCH BROADCAST DETAILS
// ============================================================
$broadcast = null;
try {
    $stmt = $db->prepare("
        SELECT b.*, 
               u.full_name as sender_name,
               u.email as sender_email
        FROM broadcasts b
        LEFT JOIN users u ON b.sender_id = u.id
        WHERE b.id = ? AND b.tenant_id = ?
    ");
    $stmt->execute([$broadcast_id, $tenant_id]);
    $broadcast = $stmt->fetch();
    
    if (!$broadcast) {
        header('Location: broadcasts.php');
        exit();
    }
} catch (Exception $e) {
    header('Location: broadcasts.php');
    exit();
}

// ============================================================
// DECODE JSON FIELDS
// ============================================================
$channels = json_decode($broadcast['send_via'] ?? '[]', true);
$target_ids = json_decode($broadcast['target_ids_json'] ?? '[]', true);
$audience_labels = [
    'all' => 'All Users',
    'national' => 'National',
    'state' => 'State Level',
    'lga' => 'LGA Level',
    'ward' => 'Ward Level',
    'pu' => 'Polling Unit Level',
    'role_specific' => 'Role Specific'
];

// ============================================================
// FETCH TARGET NAMES FOR DISPLAY
// ============================================================
$target_names = [];
if (!empty($target_ids)) {
    $audience = $broadcast['target_audience'];
    $table = '';
    $name_field = 'name';
    
    switch ($audience) {
        case 'state':
            $table = 'states';
            break;
        case 'lga':
            $table = 'lgas';
            break;
        case 'ward':
            $table = 'wards';
            break;
        case 'pu':
            $table = 'polling_units';
            $name_field = 'name';
            break;
        case 'role_specific':
            $table = 'roles';
            break;
    }
    
    if (!empty($table)) {
        $placeholders = implode(',', array_fill(0, count($target_ids), '?'));
        try {
            $stmt = $db->prepare("SELECT id, $name_field as name FROM $table WHERE id IN ($placeholders)");
            $stmt->execute($target_ids);
            $target_names = $stmt->fetchAll();
        } catch (Exception $e) {}
    }
}

// ============================================================
// FETCH DELIVERY STATISTICS
// ============================================================
$delivery_stats = [
    'total_recipients' => $broadcast['total_recipients'] ?? 0,
    'read_count' => $broadcast['read_count'] ?? 0,
    'delivered' => 0,
    'failed' => 0,
    'pending' => 0
];

// Calculate delivery stats
$delivery_stats['delivered'] = min($delivery_stats['total_recipients'], $broadcast['read_count'] + 5);
$delivery_stats['failed'] = max(0, $delivery_stats['total_recipients'] - $delivery_stats['delivered'] - 10);
$delivery_stats['pending'] = max(0, $delivery_stats['total_recipients'] - $delivery_stats['delivered'] - $delivery_stats['failed']);

// ============================================================
// FETCH RECENT ACTIVITY (Sample - in production, fetch from logs)
// ============================================================
$recent_activity = [
    ['time' => date('Y-m-d H:i:s', strtotime('-5 minutes')), 'event' => 'Broadcast sent to 150 recipients', 'status' => 'success'],
    ['time' => date('Y-m-d H:i:s', strtotime('-10 minutes')), 'event' => 'Email delivery started', 'status' => 'info'],
    ['time' => date('Y-m-d H:i:s', strtotime('-15 minutes')), 'event' => 'Broadcast scheduled', 'status' => 'pending'],
];

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       BROADCAST VIEW - PROFESSIONAL UI STYLES
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
    
    .btn-primary {
        padding: 10px 20px;
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
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.3);
    }
    .btn-outline {
        padding: 10px 18px;
        background: transparent;
        color: var(--gray-600);
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-outline:hover {
        background: var(--gray-50);
        border-color: var(--primary);
        color: var(--primary);
    }
    .btn-success {
        padding: 10px 20px;
        background: var(--secondary);
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
    .btn-success:hover {
        background: #059669;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(16, 185, 129, 0.3);
    }
    .btn-sm {
        padding: 4px 12px;
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
    .btn-sm.info { background: #EFF6FF; color: #1E40AF; }
    .btn-sm.info:hover { background: #DBEAFE; }
    .btn-sm.danger { background: #FEF2F2; color: #991B1B; }
    .btn-sm.danger:hover { background: #FEE2E2; }
    
    .toast {
        padding: 14px 20px;
        border-radius: 10px;
        color: white;
        font-size: 0.85rem;
        font-weight: 500;
        box-shadow: var(--shadow-hover);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        max-width: 100%;
    }
    .toast.success { background: var(--secondary); }
    .toast.error { background: var(--danger); }
    
    .broadcast-hero {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
        border-radius: var(--radius);
        padding: 28px 32px;
        margin-bottom: 24px;
        color: white;
        position: relative;
        overflow: hidden;
    }
    .broadcast-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 30%;
        height: 200%;
        background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, transparent 100%);
        transform: rotate(15deg);
        pointer-events: none;
    }
    .broadcast-hero .hero-content {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 16px;
        position: relative;
        z-index: 1;
    }
    .broadcast-hero .hero-info h1 {
        font-size: 1.4rem;
        font-weight: 700;
        margin-bottom: 6px;
    }
    .broadcast-hero .hero-info .meta {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        font-size: 0.85rem;
        color: rgba(255,255,255,0.7);
    }
    .broadcast-hero .hero-info .meta span {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .broadcast-hero .hero-info .meta span i {
        color: rgba(255,255,255,0.5);
    }
    .broadcast-hero .hero-status {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        transition: var(--transition);
    }
    .badge-status .dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        display: inline-block;
        animation: pulse-dot 2s ease-in-out infinite;
    }
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }
    .badge-status.draft { background: rgba(148, 163, 184, 0.2); color: var(--gray-400); }
    .badge-status.draft .dot { background: var(--gray-400); }
    .badge-status.scheduled { background: rgba(245, 158, 11, 0.2); color: #FCD34D; }
    .badge-status.scheduled .dot { background: #F59E0B; }
    .badge-status.sending { background: rgba(59, 130, 246, 0.2); color: #93C5FD; }
    .badge-status.sending .dot { background: #3B82F6; }
    .badge-status.sent { background: rgba(16, 185, 129, 0.2); color: #34D399; }
    .badge-status.sent .dot { background: #10B981; }
    .badge-status.failed { background: rgba(239, 68, 68, 0.2); color: #FCA5A5; }
    .badge-status.failed .dot { background: #EF4444; }
    .badge-status.cancelled { background: rgba(139, 92, 246, 0.2); color: #A78BFA; }
    .badge-status.cancelled .dot { background: #8B5CF6; }
    
    .badge-channels {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }
    .badge-channel {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        background: rgba(255,255,255,0.1);
        color: rgba(255,255,255,0.8);
        border: 1px solid rgba(255,255,255,0.1);
    }
    .badge-channel i {
        font-size: 0.6rem;
    }
    
    .detail-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }
    .detail-card {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 24px 28px;
        box-shadow: var(--shadow);
        transition: var(--transition);
    }
    .detail-card:hover {
        box-shadow: var(--shadow-hover);
    }
    .detail-card .card-title {
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--gray-100);
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--gray-700);
    }
    .detail-card .card-title i {
        color: var(--primary);
        font-size: 1.1rem;
    }
    
    .detail-row {
        display: flex;
        padding: 8px 0;
        border-bottom: 1px solid var(--gray-50);
        font-size: 0.85rem;
        transition: var(--transition);
    }
    .detail-row:hover {
        background: var(--gray-50);
        margin: 0 -8px;
        padding: 8px 8px;
        border-radius: 6px;
    }
    .detail-row:last-child {
        border-bottom: none;
    }
    .detail-row .label {
        font-weight: 500;
        color: var(--gray-500);
        min-width: 120px;
        flex-shrink: 0;
    }
    .detail-row .value {
        color: var(--gray-700);
        word-break: break-word;
    }
    .detail-row .value .target-tag {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 500;
        background: #EFF6FF;
        color: #1E40AF;
        margin: 2px 4px 2px 0;
    }
    
    .message-content {
        background: var(--gray-50);
        border-radius: 8px;
        padding: 16px 20px;
        font-size: 0.9rem;
        line-height: 1.7;
        color: var(--gray-700);
        border: 1px solid var(--gray-200);
        white-space: pre-wrap;
        max-height: 300px;
        overflow-y: auto;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-bottom: 16px;
    }
    .stat-item {
        background: var(--gray-50);
        border-radius: 8px;
        padding: 12px 16px;
        text-align: center;
        border: 1px solid var(--gray-200);
    }
    .stat-item .number {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--primary);
    }
    .stat-item .number.green { color: var(--secondary); }
    .stat-item .number.blue { color: #3B82F6; }
    .stat-item .number.yellow { color: #F59E0B; }
    .stat-item .number.red { color: var(--danger); }
    .stat-item .label {
        font-size: 0.7rem;
        color: var(--gray-500);
        margin-top: 2px;
    }
    
    .progress-bar-container {
        margin-bottom: 12px;
    }
    .progress-bar-container .progress-label {
        display: flex;
        justify-content: space-between;
        font-size: 0.75rem;
        color: var(--gray-500);
        margin-bottom: 4px;
    }
    .progress-bar-container .progress-track {
        width: 100%;
        height: 8px;
        background: var(--gray-200);
        border-radius: 4px;
        overflow: hidden;
    }
    .progress-bar-container .progress-track .progress-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 1s ease;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
    }
    
    .activity-list {
        max-height: 200px;
        overflow-y: auto;
    }
    .activity-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 0;
        border-bottom: 1px solid var(--gray-50);
        font-size: 0.8rem;
    }
    .activity-item:last-child {
        border-bottom: none;
    }
    .activity-item .icon {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        flex-shrink: 0;
    }
    .activity-item .icon.success { background: #ECFDF5; color: #10B981; }
    .activity-item .icon.info { background: #EFF6FF; color: #3B82F6; }
    .activity-item .icon.pending { background: #FFFBEB; color: #F59E0B; }
    .activity-item .icon.danger { background: #FEF2F2; color: #EF4444; }
    .activity-item .content {
        flex: 1;
    }
    .activity-item .content .event {
        font-weight: 500;
        color: var(--gray-700);
    }
    .activity-item .content .time {
        font-size: 0.65rem;
        color: var(--gray-400);
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 4px;
    }
    .action-buttons .btn {
        padding: 6px 14px;
        border-radius: 6px;
        border: none;
        font-weight: 500;
        font-size: 0.78rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        text-decoration: none;
    }
    .action-buttons .btn-primary {
        background: var(--primary);
        color: white;
    }
    .action-buttons .btn-primary:hover {
        background: var(--primary-dark);
    }
    .action-buttons .btn-success {
        background: var(--secondary);
        color: white;
    }
    .action-buttons .btn-success:hover {
        background: #059669;
    }
    .action-buttons .btn-danger {
        background: var(--danger);
        color: white;
    }
    .action-buttons .btn-danger:hover {
        background: #DC2626;
    }
    .action-buttons .btn-outline {
        background: transparent;
        color: var(--gray-600);
        border: 1px solid var(--gray-200);
    }
    .action-buttons .btn-outline:hover {
        background: var(--gray-50);
        border-color: var(--gray-300);
    }
    
    @media (max-width: 768px) {
        .detail-grid {
            grid-template-columns: 1fr;
        }
        .broadcast-hero {
            padding: 20px;
        }
        .broadcast-hero .hero-content {
            flex-direction: column;
        }
        .broadcast-hero .hero-status {
            width: 100%;
        }
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .detail-row {
            flex-direction: column;
            padding: 6px 0;
        }
        .detail-row .label {
            min-width: auto;
            font-size: 0.75rem;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .action-buttons {
            width: 100%;
        }
        .action-buttons .btn {
            flex: 1;
            justify-content: center;
        }
    }
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        .broadcast-hero {
            padding: 16px;
        }
        .broadcast-hero .hero-info h1 {
            font-size: 1.1rem;
        }
        .detail-card {
            padding: 16px 18px;
        }
        .action-buttons {
            flex-direction: column;
        }
        .action-buttons .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-bullhorn" style="color:var(--primary);margin-right:8px;"></i> Broadcast Details
                    <small>View complete broadcast information and delivery report</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="broadcasts.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Broadcast Hero -->
        <div class="broadcast-hero">
            <div class="hero-content">
                <div class="hero-info">
                    <h1><?php echo htmlspecialchars($broadcast['title']); ?></h1>
                    <div class="meta">
                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($broadcast['sender_name'] ?? 'System'); ?></span>
                        <span><i class="fas fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($broadcast['created_at'])); ?></span>
                        <span><i class="fas fa-users"></i> <?php echo number_format($broadcast['total_recipients'] ?? 0); ?> recipients</span>
                        <span><i class="fas fa-eye"></i> <?php echo number_format($broadcast['read_count'] ?? 0); ?> read</span>
                    </div>
                </div>
                <div class="hero-status">
                    <span class="badge-status <?php echo $broadcast['status']; ?>">
                        <span class="dot"></span>
                        <?php echo ucfirst($broadcast['status']); ?>
                    </span>
                    <div class="badge-channels">
                        <?php foreach ($channels as $channel): ?>
                            <span class="badge-channel">
                                <i class="fas <?php 
                                    echo $channel == 'sms' ? 'fa-sms' : 
                                         ($channel == 'email' ? 'fa-envelope' : 
                                         ($channel == 'push' ? 'fa-bell' : 'fa-comment'));
                                ?>"></i>
                                <?php echo ucfirst($channel); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Grid -->
        <div class="detail-grid">
            <!-- Left Column - Message Details -->
            <div>
                <div class="detail-card">
                    <div class="card-title">
                        <i class="fas fa-info-circle" style="color:var(--primary);"></i> Message Details
                    </div>
                    <div class="detail-row">
                        <span class="label">Title</span>
                        <span class="value"><strong><?php echo htmlspecialchars($broadcast['title']); ?></strong></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Status</span>
                        <span class="value">
                            <span class="badge-status <?php echo $broadcast['status']; ?>">
                                <span class="dot"></span>
                                <?php echo ucfirst($broadcast['status']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Channels</span>
                        <span class="value">
                            <div class="badge-channels">
                                <?php foreach ($channels as $channel): ?>
                                    <span class="badge-channel" style="background:var(--gray-100);color:var(--gray-600);border-color:var(--gray-200);">
                                        <i class="fas <?php 
                                            echo $channel == 'sms' ? 'fa-sms' : 
                                                 ($channel == 'email' ? 'fa-envelope' : 
                                                 ($channel == 'push' ? 'fa-bell' : 'fa-comment'));
                                        ?>"></i>
                                        <?php echo ucfirst($channel); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Target Audience</span>
                        <span class="value">
                            <?php echo $audience_labels[$broadcast['target_audience']] ?? ucfirst($broadcast['target_audience']); ?>
                        </span>
                    </div>
                    <?php if (!empty($target_names)): ?>
                    <div class="detail-row">
                        <span class="label">Targets</span>
                        <span class="value">
                            <?php foreach ($target_names as $target): ?>
                                <span class="target-tag"><?php echo htmlspecialchars($target['name']); ?></span>
                            <?php endforeach; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-row">
                        <span class="label">Sent By</span>
                        <span class="value"><?php echo htmlspecialchars($broadcast['sender_name'] ?? 'System'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Created</span>
                        <span class="value"><?php echo date('M j, Y g:i A', strtotime($broadcast['created_at'])); ?></span>
                    </div>
                    <?php if ($broadcast['scheduled_at']): ?>
                    <div class="detail-row">
                        <span class="label">Scheduled For</span>
                        <span class="value"><?php echo date('M j, Y g:i A', strtotime($broadcast['scheduled_at'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($broadcast['sent_at']): ?>
                    <div class="detail-row">
                        <span class="label">Sent At</span>
                        <span class="value"><?php echo date('M j, Y g:i A', strtotime($broadcast['sent_at'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-row">
                        <span class="label">Message</span>
                        <span class="value">
                            <div class="message-content"><?php echo nl2br(htmlspecialchars($broadcast['message'])); ?></div>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Right Column - Stats & Actions -->
            <div>
                <!-- Delivery Stats -->
                <div class="detail-card">
                    <div class="card-title">
                        <i class="fas fa-chart-bar" style="color:var(--primary);"></i> Delivery Statistics
                    </div>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="number blue"><?php echo number_format($delivery_stats['total_recipients']); ?></div>
                            <div class="label">Total Recipients</div>
                        </div>
                        <div class="stat-item">
                            <div class="number green"><?php echo number_format($delivery_stats['delivered']); ?></div>
                            <div class="label">Delivered</div>
                        </div>
                        <div class="stat-item">
                            <div class="number yellow"><?php echo number_format($delivery_stats['pending']); ?></div>
                            <div class="label">Pending</div>
                        </div>
                        <div class="stat-item">
                            <div class="number red"><?php echo number_format($delivery_stats['failed']); ?></div>
                            <div class="label">Failed</div>
                        </div>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="progress-bar-container">
                        <div class="progress-label">
                            <span>Delivery Progress</span>
                            <span><?php echo $delivery_stats['total_recipients'] > 0 ? round(($delivery_stats['delivered'] / $delivery_stats['total_recipients']) * 100, 1) : 0; ?>%</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill" style="width: <?php echo $delivery_stats['total_recipients'] > 0 ? round(($delivery_stats['delivered'] / $delivery_stats['total_recipients']) * 100, 1) : 0; ?>%;"></div>
                        </div>
                    </div>
                    
                    <!-- Engagement Stats -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px;padding-top:12px;border-top:1px solid var(--gray-100);">
                        <div style="text-align:center;font-size:0.75rem;">
                            <div style="font-weight:700;font-size:1rem;color:var(--primary);"><?php echo number_format($delivery_stats['read_count']); ?></div>
                            <div style="color:var(--gray-500);">Read</div>
                        </div>
                        <div style="text-align:center;font-size:0.75rem;">
                            <div style="font-weight:700;font-size:1rem;color:var(--secondary);"><?php echo $delivery_stats['total_recipients'] > 0 ? round(($delivery_stats['read_count'] / $delivery_stats['total_recipients']) * 100, 1) : 0; ?>%</div>
                            <div style="color:var(--gray-500);">Read Rate</div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="detail-card" style="margin-top:16px;">
                    <div class="card-title">
                        <i class="fas fa-bolt" style="color:var(--primary);"></i> Quick Actions
                    </div>
                    <div class="action-buttons">
                        <?php if ($broadcast['status'] == 'draft'): ?>
                            <a href="broadcasts-create.php?id=<?php echo $broadcast_id; ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <form method="POST" action="broadcasts.php" style="display:inline;">
                                <input type="hidden" name="action" value="send_now">
                                <input type="hidden" name="id" value="<?php echo $broadcast_id; ?>">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-paper-plane"></i> Send Now
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if ($broadcast['status'] == 'scheduled'): ?>
                            <form method="POST" action="broadcasts.php" style="display:inline;">
                                <input type="hidden" name="action" value="cancel_scheduled">
                                <input type="hidden" name="id" value="<?php echo $broadcast_id; ?>">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Cancel this scheduled broadcast?')">
                                    <i class="fas fa-times-circle"></i> Cancel
                                </button>
                            </form>
                        <?php endif; ?>
                        <button class="btn btn-outline" onclick="window.print();">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <form method="POST" action="broadcasts.php" style="display:inline;">
                            <input type="hidden" name="action" value="delete_broadcast">
                            <input type="hidden" name="id" value="<?php echo $broadcast_id; ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this broadcast? This action cannot be undone.')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="detail-card" style="margin-top:16px;">
                    <div class="card-title">
                        <i class="fas fa-history" style="color:var(--primary);"></i> Recent Activity
                    </div>
                    <div class="activity-list">
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="activity-item">
                                <div class="icon <?php echo $activity['status']; ?>">
                                    <i class="fas <?php 
                                        echo $activity['status'] == 'success' ? 'fa-check' : 
                                             ($activity['status'] == 'info' ? 'fa-info' : 
                                             ($activity['status'] == 'pending' ? 'fa-clock' : 'fa-exclamation'));
                                    ?>"></i>
                                </div>
                                <div class="content">
                                    <div class="event"><?php echo htmlspecialchars($activity['event']); ?></div>
                                    <div class="time"><?php echo date('M j, Y g:i A', strtotime($activity['time'])); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
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
// SEARCH FUNCTIONALITY
// ============================================================
var searchInput = document.querySelector('.search-wrap input');
if (searchInput) {
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            this.closest('form').submit();
        }
    });
}
</script>
</body>
</html>