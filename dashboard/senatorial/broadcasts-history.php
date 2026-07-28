<?php
// ============================================================
// SENATORIAL COORDINATOR - BROADCAST HISTORY
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

$user_name = SessionManager::get('user_name', 'Coordinator');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// GET BROADCAST ID
// ============================================================
$broadcast_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$broadcast_id) {
    header('Location: broadcasts.php');
    exit();
}

// ============================================================
// GET BROADCAST DATA WITH DETAILS
// ============================================================
$broadcast = null;
try {
    $stmt = $db->prepare("
        SELECT b.*, u.full_name as sender_name
        FROM broadcasts b
        LEFT JOIN users u ON b.sender_id = u.id
        WHERE b.id = ? AND b.tenant_id = ?
    ");
    $stmt->execute([$broadcast_id, $tenant_id]);
    $broadcast = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching broadcast: " . $e->getMessage());
}

if (!$broadcast) {
    header('Location: broadcasts.php');
    exit();
}

// ============================================================
// GET DELIVERY LOGS
// ============================================================
$delivery_logs = [];
try {
    // This would typically come from a delivery_logs table
    // For now, we'll show a placeholder message
    $delivery_logs = [];
} catch (Exception $e) {
    error_log("Error fetching delivery logs: " . $e->getMessage());
}

$page_title = 'Broadcast History';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
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

.history-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
}
.history-card .broadcast-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--gray-200);
}
.history-card .broadcast-header .title {
    font-size: 1.1rem;
    font-weight: 700;
}
.history-card .broadcast-header .status {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}
.history-card .broadcast-header .status.sent { background: #D1FAE5; color: #059669; }
.history-card .broadcast-header .status.scheduled { background: #DBEAFE; color: #2563EB; }
.history-card .broadcast-header .status.draft { background: var(--gray-200); color: var(--gray-600); }
.history-card .broadcast-header .status.failed { background: #FEE2E2; color: #DC2626; }

.history-detail {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin: 16px 0;
}
.history-detail .detail-item {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 12px 16px;
}
.history-detail .detail-item .label {
    font-size: 0.7rem;
    color: var(--gray-400);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.history-detail .detail-item .value {
    font-weight: 600;
    font-size: 0.9rem;
}

.history-message {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 16px;
    margin: 16px 0;
}
.history-message .label {
    font-size: 0.7rem;
    color: var(--gray-400);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}
.history-message .message {
    color: var(--gray-700);
    white-space: pre-wrap;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: var(--gray-500);
}
.empty-state i {
    font-size: 3rem;
    display: block;
    margin-bottom: 12px;
    color: var(--gray-300);
}

.btn-secondary {
    padding: 8px 18px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--gray-100);
    color: var(--gray-600);
    border: none;
}
.btn-secondary:hover {
    background: var(--gray-200);
}

@media (max-width: 768px) {
    .history-detail {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-history" style="color:var(--primary);margin-right:8px;"></i> 
                    Broadcast History
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="broadcasts.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Broadcasts
                </a>
            </div>
        </div>

        <div class="history-card">
            <!-- Broadcast Header -->
            <div class="broadcast-header">
                <div>
                    <div class="title"><?php echo htmlspecialchars($broadcast['title']); ?></div>
                    <div style="font-size:0.8rem;color:var(--gray-500);margin-top:4px;">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($broadcast['sender_name'] ?? 'System'); ?>
                    </div>
                </div>
                <div>
                    <span class="status <?php echo $broadcast['status']; ?>">
                        <?php echo ucfirst($broadcast['status']); ?>
                    </span>
                </div>
            </div>

            <!-- Details -->
            <div class="history-detail">
                <div class="detail-item">
                    <div class="label">Created</div>
                    <div class="value"><?php echo date('M j, Y g:i A', strtotime($broadcast['created_at'])); ?></div>
                </div>
                <?php if ($broadcast['sent_at']): ?>
                    <div class="detail-item">
                        <div class="label">Sent</div>
                        <div class="value"><?php echo date('M j, Y g:i A', strtotime($broadcast['sent_at'])); ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($broadcast['scheduled_at']): ?>
                    <div class="detail-item">
                        <div class="label">Scheduled</div>
                        <div class="value"><?php echo date('M j, Y g:i A', strtotime($broadcast['scheduled_at'])); ?></div>
                    </div>
                <?php endif; ?>
                <div class="detail-item">
                    <div class="label">Recipients</div>
                    <div class="value"><?php echo number_format($broadcast['total_recipients'] ?? 0); ?></div>
                </div>
                <div class="detail-item">
                    <div class="label">Read</div>
                    <div class="value"><?php echo number_format($broadcast['read_count'] ?? 0); ?></div>
                </div>
                <div class="detail-item">
                    <div class="label">Target Audience</div>
                    <div class="value"><?php echo ucfirst(str_replace('_', ' ', $broadcast['target_audience'])); ?></div>
                </div>
                <div class="detail-item">
                    <div class="label">Delivery Methods</div>
                    <div class="value">
                        <?php 
                        $send_via = json_decode($broadcast['send_via'], true) ?: ['email'];
                        echo implode(', ', array_map('ucfirst', $send_via));
                        ?>
                    </div>
                </div>
            </div>

            <!-- Message -->
            <div class="history-message">
                <div class="label">Message</div>
                <div class="message"><?php echo nl2br(htmlspecialchars($broadcast['message'])); ?></div>
            </div>

            <!-- Delivery Logs (Placeholder) -->
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--gray-200);">
                <h4 style="font-size:0.9rem;font-weight:600;margin-bottom:12px;">
                    <i class="fas fa-list"></i> Delivery Logs
                </h4>
                <div class="empty-state" style="padding:20px;">
                    <i class="fas fa-inbox" style="font-size:2rem;"></i>
                    <p style="font-size:0.85rem;">Delivery logs will appear here once available.</p>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
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