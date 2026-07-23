<?php
// ============================================================
// WARD COORDINATOR - VIEW BROADCAST DETAILS
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
$ward_id = SessionManager::get('ward_id');
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
$error_message = '';

try {
    $stmt = $db->prepare("
        SELECT 
            b.*,
            u.full_name as sender_name,
            u.email as sender_email,
            u.phone as sender_phone
        FROM broadcasts b
        LEFT JOIN users u ON b.sender_id = u.id
        WHERE b.id = ? AND b.tenant_id = ?
    ");
    $stmt->execute([$broadcast_id, $tenant_id]);
    $broadcast = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$broadcast) {
        header('Location: broadcasts.php?error=notfound');
        exit();
    }
    
    // Update read count if viewer is not the sender
    if ($broadcast['sender_id'] != $user_id) {
        $stmt = $db->prepare("UPDATE broadcasts SET read_count = read_count + 1 WHERE id = ?");
        $stmt->execute([$broadcast_id]);
    }
    
} catch (Exception $e) {
    error_log("Error fetching broadcast: " . $e->getMessage());
    header('Location: broadcasts.php?error=db');
    exit();
}

// ============================================================
// FETCH WARD NAME
// ============================================================
$ward_name = 'Ward';
try {
    if ($ward_id) {
        $stmt = $db->prepare("SELECT name FROM wards WHERE id = ?");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching ward name: " . $e->getMessage());
}

$page_title = 'View Broadcast';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.view-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.view-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.view-header h2 i {
    color: var(--primary);
}

.broadcast-detail {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
    margin-bottom: 20px;
}
.broadcast-detail .detail-title {
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 8px;
}
.broadcast-detail .detail-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 0.8rem;
    color: var(--gray-500);
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--gray-200);
}
.broadcast-detail .detail-meta i {
    width: 16px;
}
.broadcast-detail .detail-message {
    font-size: 1rem;
    color: var(--gray-700);
    line-height: 1.8;
    white-space: pre-wrap;
    padding: 16px;
    background: var(--gray-50);
    border-radius: 6px;
    margin-bottom: 16px;
}
.broadcast-detail .detail-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    padding-top: 16px;
    border-top: 1px solid var(--gray-200);
}
.broadcast-detail .detail-info .info-item {
    font-size: 0.82rem;
}
.broadcast-detail .detail-info .info-item .label {
    color: var(--gray-500);
    font-weight: 500;
}
.broadcast-detail .detail-info .info-item .value {
    color: var(--gray-800);
}

.badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
}
.badge.draft { background: #E5E7EB; color: #374151; }
.badge.scheduled { background: #DBEAFE; color: #1E40AF; }
.badge.sent { background: #D1FAE5; color: #065F46; }
.badge.failed { background: #FEE2E2; color: #991B1B; }
.badge.cancelled { background: #E5E7EB; color: #374151; }

.view-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.view-actions .btn-sm {
    padding: 6px 16px;
    font-size: 0.8rem;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.view-actions .btn-sm.edit { background: #FEF3C7; color: #92400E; }
.view-actions .btn-sm.send { background: #D1FAE5; color: #065F46; }
.view-actions .btn-sm.delete { background: #FEE2E2; color: #991B1B; }
.view-actions .btn-sm.back { background: #E5E7EB; color: #374151; }
.view-actions .btn-sm.duplicate { background: #F5F3FF; color: #6D28D9; }

@media (max-width: 768px) {
    .broadcast-detail .detail-info {
        grid-template-columns: 1fr;
    }
    .view-actions {
        flex-direction: column;
    }
    .view-actions .btn-sm {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="view-header">
            <div>
                <h2><i class="fas fa-bullhorn"></i> Broadcast Details</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                </p>
            </div>
            <div>
                <a href="broadcasts.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Broadcasts
                </a>
            </div>
        </div>

        <?php if ($broadcast): ?>
            <!-- Broadcast Detail -->
            <div class="broadcast-detail">
                <div class="detail-title">
                    <?php echo htmlspecialchars($broadcast['title']); ?>
                    <span class="badge <?php echo $broadcast['status']; ?>" style="margin-left:12px;font-size:0.7rem;">
                        <?php echo ucfirst($broadcast['status']); ?>
                    </span>
                </div>
                
                <div class="detail-meta">
                    <span><i class="fas fa-user"></i> From: <?php echo htmlspecialchars($broadcast['sender_name'] ?? 'Unknown'); ?></span>
                    <span><i class="fas fa-clock"></i> Created: <?php echo date('M d, Y H:i:s', strtotime($broadcast['created_at'])); ?></span>
                    <?php if ($broadcast['sent_at']): ?>
                        <span><i class="fas fa-check-circle" style="color:#10B981;"></i> Sent: <?php echo date('M d, Y H:i:s', strtotime($broadcast['sent_at'])); ?></span>
                    <?php endif; ?>
                    <?php if ($broadcast['scheduled_at']): ?>
                        <span><i class="fas fa-calendar"></i> Scheduled: <?php echo date('M d, Y H:i:s', strtotime($broadcast['scheduled_at'])); ?></span>
                    <?php endif; ?>
                </div>

                <div class="detail-message">
                    <?php echo nl2br(htmlspecialchars($broadcast['message'])); ?>
                </div>

                <div class="detail-info">
                    <div class="info-item">
                        <span class="label">Target Audience</span><br>
                        <span class="value"><?php echo ucfirst(str_replace('_', ' ', $broadcast['target_audience'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Total Recipients</span><br>
                        <span class="value"><?php echo number_format($broadcast['total_recipients'] ?? 0); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Read Count</span><br>
                        <span class="value"><?php echo number_format($broadcast['read_count'] ?? 0); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Send Via</span><br>
                        <span class="value">
                            <?php 
                            $channels = json_decode($broadcast['send_via'] ?? '[]', true);
                            echo !empty($channels) ? implode(', ', array_map('ucfirst', $channels)) : 'Email';
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="view-actions">
                <a href="broadcasts.php" class="btn-sm back">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                
                <?php if ($broadcast['status'] === 'draft'): ?>
                    <a href="broadcasts-edit.php?id=<?php echo $broadcast_id; ?>" class="btn-sm edit">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="broadcasts-send.php?id=<?php echo $broadcast_id; ?>" class="btn-sm send">
                        <i class="fas fa-paper-plane"></i> Send
                    </a>
                    <a href="broadcasts-delete.php?id=<?php echo $broadcast_id; ?>" class="btn-sm delete" onclick="return confirm('Delete this broadcast?')">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                <?php endif; ?>
                
                <?php if ($broadcast['status'] === 'scheduled'): ?>
                    <a href="broadcasts-edit.php?id=<?php echo $broadcast_id; ?>" class="btn-sm edit">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="broadcasts-delete.php?id=<?php echo $broadcast_id; ?>" class="btn-sm delete" onclick="return confirm('Cancel and delete this scheduled broadcast?')">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php endif; ?>
                
                <?php if ($broadcast['status'] === 'sent'): ?>
                    <a href="broadcasts-duplicate.php?id=<?php echo $broadcast_id; ?>" class="btn-sm duplicate">
                        <i class="fas fa-copy"></i> Duplicate
                    </a>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div style="text-align:center;padding:60px 20px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);">
                <i class="fas fa-bullhorn" style="font-size:4rem;color:var(--gray-300);"></i>
                <h4 style="margin:16px 0 8px;">Broadcast Not Found</h4>
                <p style="color:var(--gray-500);">The broadcast you're looking for does not exist.</p>
                <a href="broadcasts.php" class="btn-primary-sm" style="display:inline-block;margin-top:12px;">
                    <i class="fas fa-arrow-left"></i> Back to Broadcasts
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
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