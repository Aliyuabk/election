<?php
// ============================================================
// LOGIN HISTORY - View login attempts history
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

// Get login history
$stmt = $db->prepare("SELECT * FROM login_attempts WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
$stmt->execute([$user_id]);
$history = $stmt->fetchAll();

// Get summary stats
$stmt = $db->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful,
    SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed
    FROM login_attempts WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login History - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&family=Poppins:wght@600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
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
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: white; border-radius: 32px; padding: 48px 40px; box-shadow: 0 20px 60px rgba(15, 76, 129, 0.08); border: 1px solid #E2E8F0; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
        .header h1 { font-size: 1.8rem; color: #0F4C81; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 30px; }
        .stat-card { background: #F8FAFC; padding: 16px 20px; border-radius: 12px; border: 1px solid #E2E8F0; text-align: center; }
        .stat-card .number { font-size: 1.8rem; font-weight: 700; color: #0F4C81; }
        .stat-card .label { font-size: 0.85rem; color: #64748B; }
        .stat-card .number.success { color: #10B981; }
        .stat-card .number.failed { color: #DC2626; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th { text-align: left; padding: 12px 16px; background: #F8FAFC; font-weight: 600; color: #0F172A; border-bottom: 2px solid #E2E8F0; }
        td { padding: 12px 16px; border-bottom: 1px solid #E2E8F0; }
        tr:hover { background: #F8FAFC; }
        .status-badge { display: inline-block; padding: 2px 12px; border-radius: 30px; font-size: 0.8rem; font-weight: 600; }
        .status-badge.success { background: #ECFDF5; color: #065F46; }
        .status-badge.failed { background: #FEF2F2; color: #DC2626; }
        .back-link { text-align: center; margin-top: 30px; }
        .back-link a { color: #64748B; text-decoration: none; font-size: 0.9rem; transition: 0.15s; display: inline-flex; align-items: center; gap: 8px; }
        .back-link a:hover { color: #0F4C81; }
        .empty-state { text-align: center; padding: 40px 20px; color: #64748B; }
        .empty-state i { font-size: 3rem; color: #94A3B8; margin-bottom: 16px; }
        @media (max-width: 768px) {
            .stats { grid-template-columns: 1fr; }
            .card { padding: 32px 24px; }
            .header { flex-direction: column; align-items: flex-start; }
            th, td { padding: 8px 12px; font-size: 0.8rem; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="header">
            <h1><i class="fas fa-history"></i> Login History</h1>
            <a href="login.php" class="btn-primary" style="text-decoration: none; padding: 8px 20px; border-radius: 10px; background: #F1F5F9; color: #0F172A;">
                <i class="fas fa-sign-in-alt"></i> Current Session
            </a>
        </div>
        
        <!-- Stats -->
        <div class="stats">
            <div class="stat-card">
                <div class="number"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="label">Total Login Attempts</div>
            </div>
            <div class="stat-card">
                <div class="number success"><?php echo $stats['successful'] ?? 0; ?></div>
                <div class="label">Successful Logins</div>
            </div>
            <div class="stat-card">
                <div class="number failed"><?php echo $stats['failed'] ?? 0; ?></div>
                <div class="label">Failed Logins</div>
            </div>
        </div>
        
        <!-- History Table -->
        <?php if (empty($history)): ?>
        <div class="empty-state">
            <i class="fas fa-history"></i>
            <p>No login history found.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>IP Address</th>
                        <th>Device</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $record): ?>
                    <tr>
                        <td><?php echo date('M d, Y H:i:s', strtotime($record['created_at'])); ?></td>
                        <td><code><?php echo htmlspecialchars($record['ip_address']); ?></code></td>
                        <td><?php echo htmlspecialchars($record['user_agent'] ? substr($record['user_agent'], 0, 50) . '...' : 'Unknown'); ?></td>
                        <td>
                            <span class="status-badge <?php echo $record['success'] ? 'success' : 'failed'; ?>">
                                <?php echo $record['success'] ? '✅ Success' : '❌ Failed'; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="../dashboard/index.php">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>
</body>
</html>