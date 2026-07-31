<?php
// ============================================================
// FEDERAL CONSTITUENCY COORDINATOR - BROADCASTS
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

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$constituency_id = SessionManager::get('federal_constituency_id');
$state_id = SessionManager::get('state_id');

$db = getDB();

// ============================================================
// GET BROADCASTS
// ============================================================
$broadcasts = [];
try {
    $stmt = $db->prepare("
        SELECT b.*, u.full_name as sender_name
        FROM broadcasts b
        LEFT JOIN users u ON b.sender_id = u.id
        WHERE b.tenant_id = ?
        ORDER BY b.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$tenant_id]);
    $broadcasts = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching broadcasts: " . $e->getMessage());
}

// ============================================================
// HANDLE DELETE
// ============================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $db->prepare("DELETE FROM broadcasts WHERE id = ? AND tenant_id = ? AND status = 'draft'");
        $stmt->execute([$id, $tenant_id]);
        $success = 'Broadcast deleted successfully.';
        header('Location: broadcasts.php?deleted=1');
        exit();
    } catch (Exception $e) {
        error_log("Error deleting broadcast: " . $e->getMessage());
    }
}

$page_title = 'Broadcasts';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.broadcast-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 14px;
    margin-bottom: 24px;
}
.broadcast-stat {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    text-align: center;
}
.broadcast-stat .number {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--gray-800);
}
.broadcast-stat .label {
    font-size: 0.7rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-section {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 14px 18px;
    margin-bottom: 20px;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}
.filter-section input {
    padding: 8px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.85rem;
    flex: 1;
    min-width: 150px;
}
.filter-section input:focus {
    outline: none;
    border-color: var(--primary);
}
.filter-section .btn-filter {
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    background: var(--primary);
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
}
.filter-section .btn-reset {
    padding: 8px 18px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    background: white;
    color: var(--gray-600);
    font-weight: 500;
    font-size: 0.8rem;
    text-decoration: none;
}

.broadcast-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 18px 20px;
    margin-bottom: 14px;
    transition: var(--transition);
}
.broadcast-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow);
}
.broadcast-card .broadcast-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 8px;
}
.broadcast-card .broadcast-title {
    font-weight: 600;
    font-size: 0.95rem;
}
.broadcast-card .broadcast-meta {
    display: flex;
    gap: 16px;
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-top: 4px;
    flex-wrap: wrap;
}
.broadcast-card .broadcast-meta i {
    margin-right: 4px;
}
.broadcast-card .broadcast-message {
    margin-top: 10px;
    font-size: 0.85rem;
    color: var(--gray-600);
    line-height: 1.5;
}
.broadcast-card .broadcast-actions {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--gray-100);
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.broadcast-card .broadcast-actions a {
    padding: 4px 14px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    text-decoration: none;
}
.broadcast-card .broadcast-actions .btn-edit {
    background: var(--gray-100);
    color: var(--gray-600);
}
.broadcast-card .broadcast-actions .btn-edit:hover {
    background: var(--gray-200);
}
.broadcast-card .broadcast-actions .btn-send {
    background: var(--primary);
    color: white;
}
.broadcast-card .broadcast-actions .btn-send:hover {
    background: var(--primary-dark);
}
.broadcast-card .broadcast-actions .btn-delete {
    background: #FEE2E2;
    color: #DC2626;
}
.broadcast-card .broadcast-actions .btn-delete:hover {
    background: #FECACA;
}

.status-badge {
    display: inline-block;
    padding: 2px 12px;
    border-radius: 12px;
    font-size: 0.65rem;
    font-weight: 600;
}
.status-badge.draft { background: #F3F4F6; color: #6B7280; }
.status-badge.sent { background: #D1FAE5; color: #059669; }
.status-badge.scheduled { background: #DBEAFE; color: #2563EB; }
.status-badge.failed { background: #FEE2E2; color: #DC2626; }

.priority-badge {
    display: inline-block;
    padding: 1px 10px;
    border-radius: 10px;
    font-size: 0.6rem;
    font-weight: 600;
}
.priority-badge.low { background: #F3F4F6; color: #6B7280; }
.priority-badge.medium { background: #DBEAFE; color: #2563EB; }
.priority-badge.high { background: #FEF3C7; color: #D97706; }
.priority-badge.emergency { background: #FEE2E2; color: #DC2626; animation: pulse-red 1.5s infinite; }

@keyframes pulse-red {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-500);
}
.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    display: block;
    margin-bottom: 12px;
}
.empty-state h3 {
    font-size: 1.1rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}

.quick-actions-row {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.quick-actions-row .btn {
    padding: 10px 20px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    cursor: pointer;
}
.quick-actions-row .btn-primary {
    background: var(--primary);
    color: white;
}
.quick-actions-row .btn-primary:hover {
    background: var(--primary-dark);
}
.quick-actions-row .btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.quick-actions-row .btn-secondary:hover {
    background: var(--gray-200);
}

@media (max-width: 768px) {
    .filter-section {
        flex-direction: column;
    }
    .filter-section input {
        width: 100%;
    }
    .broadcast-card .broadcast-header {
        flex-direction: column;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:4px;">
            <h2 style="font-size:1.3rem;font-weight:700;margin:0;">
                <i class="fas fa-bullhorn" style="color:var(--primary);"></i> Broadcasts
            </h2>
        </div>
        <p style="color:var(--gray-500);font-size:0.9rem;margin-bottom:20px;">
            Send announcements and instructions to coordinators in your constituency.
        </p>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success" style="padding:12px 16px;border-radius:10px;background:#ECFDF5;color:#065F46;border:1px solid #A7F3D0;margin-bottom:16px;">
                <i class="fas fa-check-circle"></i> Broadcast deleted successfully.
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="quick-actions-row">
            <a href="broadcasts-create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create Broadcast
            </a>
            <a href="broadcasts-schedule.php" class="btn btn-secondary">
                <i class="fas fa-calendar-plus"></i> Schedule
            </a>
        </div>

        <!-- Stats -->
        <?php 
        $total = count($broadcasts);
        $sent = 0;
        $draft = 0;
        $scheduled = 0;
        foreach ($broadcasts as $b) {
            if ($b['status'] === 'sent') $sent++;
            elseif ($b['status'] === 'draft') $draft++;
            elseif ($b['status'] === 'scheduled') $scheduled++;
        }
        ?>
        <div class="broadcast-stats">
            <div class="broadcast-stat">
                <div class="number"><?php echo $total; ?></div>
                <div class="label">Total</div>
            </div>
            <div class="broadcast-stat">
                <div class="number" style="color:#059669;"><?php echo $sent; ?></div>
                <div class="label">Sent</div>
            </div>
            <div class="broadcast-stat">
                <div class="number" style="color:#6B7280;"><?php echo $draft; ?></div>
                <div class="label">Drafts</div>
            </div>
            <div class="broadcast-stat">
                <div class="number" style="color:#2563EB;"><?php echo $scheduled; ?></div>
                <div class="label">Scheduled</div>
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-section">
            <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;flex:1;align-items:center;">
                <input type="text" name="search" placeholder="Search broadcasts..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Search</button>
                <a href="broadcasts.php" class="btn-reset"><i class="fas fa-times"></i> Clear</a>
            </form>
        </div>

        <!-- Broadcasts List -->
        <?php if (count($broadcasts) > 0): ?>
            <?php foreach ($broadcasts as $broadcast): ?>
                <div class="broadcast-card">
                    <div class="broadcast-header">
                        <div>
                            <div class="broadcast-title">
                                <?php echo htmlspecialchars($broadcast['title']); ?>
                            </div>
                            <div class="broadcast-meta">
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($broadcast['sender_name'] ?? 'System'); ?></span>
                                <span><i class="fas fa-clock"></i> <?php echo date('M d, Y g:i A', strtotime($broadcast['created_at'])); ?></span>
                                <span><i class="fas fa-users"></i> <?php echo ucfirst(str_replace('_', ' ', $broadcast['target_audience'] ?? 'All')); ?></span>
                                <span><span class="status-badge <?php echo $broadcast['status']; ?>"><?php echo ucfirst($broadcast['status']); ?></span></span>
                                <span><span class="priority-badge <?php echo $broadcast['priority'] ?? 'low'; ?>"><?php echo ucfirst($broadcast['priority'] ?? 'Low'); ?></span></span>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <?php if ($broadcast['status'] === 'sent'): ?>
                                <span style="font-size:0.75rem;color:var(--gray-400);">
                                    <i class="fas fa-check-circle" style="color:#10B981;"></i>
                                    <?php echo number_format($broadcast['total_recipients'] ?? 0); ?> recipients
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="broadcast-message">
                        <?php 
                        $message = htmlspecialchars($broadcast['message']);
                        if (strlen($message) > 200) {
                            echo substr($message, 0, 200) . '...';
                        } else {
                            echo $message;
                        }
                        ?>
                    </div>
                    
                    <?php if ($broadcast['status'] === 'draft' || $broadcast['status'] === 'scheduled'): ?>
                        <div class="broadcast-actions">
                            <a href="broadcasts-edit.php?id=<?php echo $broadcast['id']; ?>" class="btn-edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <?php if ($broadcast['status'] === 'draft'): ?>
                                <a href="broadcasts-send.php?id=<?php echo $broadcast['id']; ?>" class="btn-send">
                                    <i class="fas fa-paper-plane"></i> Send Now
                                </a>
                            <?php endif; ?>
                            <a href="broadcasts.php?delete=<?php echo $broadcast['id']; ?>" class="btn-delete" 
                               onclick="return confirm('Delete this broadcast?')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-bullhorn"></i>
                <h3>No Broadcasts Yet</h3>
                <p>Create your first broadcast to communicate with coordinators.</p>
                <a href="broadcasts-create.php" style="display:inline-block;margin-top:12px;padding:10px 24px;background:var(--primary);color:white;border-radius:10px;text-decoration:none;font-weight:600;">
                    <i class="fas fa-plus"></i> Create Broadcast
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
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