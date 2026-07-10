<?php
// ============================================================
// STATE COORDINATOR - BROADCASTS LIST
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

if (empty($state_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT state_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['state_id'])) {
            $state_id = $user['state_id'];
            SessionManager::set('state_id', $state_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching state_id: " . $e->getMessage());
    }
}

$db = getDB();
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Get state name
$state_name = 'Unknown State';
if (!empty($state_id)) {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
}

// Fetch broadcasts
$broadcasts = [];
$stats = [
    'total' => 0,
    'sent' => 0,
    'scheduled' => 0,
    'draft' => 0,
    'failed' => 0
];

try {
    $sql = "
        SELECT 
            b.*,
            u.first_name as sender_first_name,
            u.last_name as sender_last_name,
            (SELECT COUNT(*) FROM notifications WHERE broadcast_id = b.id AND is_read = 1) as read_count
        FROM broadcasts b
        LEFT JOIN users u ON b.sender_id = u.id
        WHERE b.tenant_id = ?
        AND (b.target_ids_json LIKE ? OR b.target_ids_json IS NULL OR b.target_audience IN ('all', 'state'))
    ";
    
    $params = [$tenant_id, '%"' . $state_id . '"%'];
    
    if (!empty($status_filter)) {
        $sql .= " AND b.status = ?";
        $params[] = $status_filter;
    }
    
    $sql .= " ORDER BY b.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $broadcasts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($broadcasts as $b) {
        $stats['total']++;
        $stats[$b['status']] = ($stats[$b['status']] ?? 0) + 1;
    }
} catch (Exception $e) {
    error_log("Error fetching broadcasts: " . $e->getMessage());
}

$status_colors = [
    'draft' => 'secondary',
    'scheduled' => 'warning',
    'sending' => 'info',
    'sent' => 'success',
    'failed' => 'danger',
    'cancelled' => 'danger'
];

$target_labels = [
    'all' => 'All Users',
    'national' => 'National',
    'state' => 'State',
    'senatorial' => 'Senatorial',
    'federal_constituency' => 'Federal Constituency',
    'lga' => 'LGA',
    'ward' => 'Ward',
    'pu' => 'Polling Unit',
    'role_specific' => 'Specific Role'
];

$page_title = 'Broadcasts';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.filter-bar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    align-items: center;
    background: white;
    padding: 16px 20px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}

.filter-bar select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.8rem;
    font-family: 'Inter', sans-serif;
    background: white;
    min-width: 150px;
}

.filter-bar select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.filter-bar .filter-info {
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-left: auto;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}

.stats-row .stat-box {
    background: white;
    border-radius: 10px;
    padding: 10px 14px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

.stats-row .stat-box .number {
    font-size: 1.2rem;
    font-weight: 700;
}

.stats-row .stat-box .number.total { color: #3B82F6; }
.stats-row .stat-box .number.sent { color: #10B981; }
.stats-row .stat-box .number.scheduled { color: #F59E0B; }
.stats-row .stat-box .number.draft { color: #6B7280; }
.stats-row .stat-box .number.failed { color: #EF4444; }

.stats-row .stat-box .label {
    font-size: 0.6rem;
    color: var(--gray-500);
}

.broadcast-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 16px;
}

.broadcast-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 18px 20px;
    transition: var(--transition);
}

.broadcast-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
}

.broadcast-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.broadcast-card .card-header .title {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--gray-800);
}

.broadcast-card .card-header .title i {
    color: var(--primary);
    margin-right: 6px;
}

.broadcast-card .message-preview {
    font-size: 0.78rem;
    color: var(--gray-600);
    margin: 6px 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.broadcast-card .meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px;
    margin: 8px 0;
    padding: 8px 0;
    border-top: 1px solid var(--gray-100);
    border-bottom: 1px solid var(--gray-100);
}

.broadcast-card .meta .meta-item {
    font-size: 0.65rem;
    color: var(--gray-500);
}

.broadcast-card .meta .meta-item .value {
    font-weight: 500;
    color: var(--gray-700);
}

.broadcast-card .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.55rem;
    padding: 2px 10px;
    border-radius: 12px;
    font-weight: 600;
}

.broadcast-card .status-badge .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.broadcast-card .status-badge.draft { background: #F3F4F6; color: #6B7280; }
.broadcast-card .status-badge.draft .dot { background: #9CA3AF; }
.broadcast-card .status-badge.scheduled { background: #FFFBEB; color: #92400E; }
.broadcast-card .status-badge.scheduled .dot { background: #F59E0B; }
.broadcast-card .status-badge.sending { background: #EFF6FF; color: #1E40AF; }
.broadcast-card .status-badge.sending .dot { background: #3B82F6; animation: pulse-dot 1.5s ease-in-out infinite; }
.broadcast-card .status-badge.sent { background: #ECFDF5; color: #065F46; }
.broadcast-card .status-badge.sent .dot { background: #10B981; }
.broadcast-card .status-badge.failed { background: #FEF2F2; color: #991B1B; }
.broadcast-card .status-badge.failed .dot { background: #EF4444; }
.broadcast-card .status-badge.cancelled { background: #FEF2F2; color: #991B1B; }
.broadcast-card .status-badge.cancelled .dot { background: #EF4444; }

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.8); }
}

.broadcast-card .card-actions {
    display: flex;
    gap: 6px;
    margin-top: 10px;
    flex-wrap: wrap;
}

.broadcast-card .card-actions a {
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 0.65rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
}

.broadcast-card .card-actions .btn-edit {
    background: var(--gray-100);
    color: var(--gray-700);
}

.broadcast-card .card-actions .btn-edit:hover {
    background: var(--gray-200);
}

.broadcast-card .card-actions .btn-send {
    background: #3B82F6;
    color: white;
}

.broadcast-card .card-actions .btn-send:hover {
    background: #2563EB;
}

.broadcast-card .card-actions .btn-delete {
    background: #FEF2F2;
    color: #DC2626;
}

.broadcast-card .card-actions .btn-delete:hover {
    background: #FEE2E2;
}

.broadcast-card .card-actions .btn-view {
    background: var(--gray-100);
    color: var(--gray-700);
}

.broadcast-card .card-actions .btn-view:hover {
    background: var(--gray-200);
}

.broadcast-card .card-actions .btn-schedule {
    background: #FFFBEB;
    color: #D97706;
}

.broadcast-card .card-actions .btn-schedule:hover {
    background: #FEF3C7;
}

.empty-state {
    grid-column: 1/-1;
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}

.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    display: block;
    margin-bottom: 12px;
}

.empty-state h3 {
    color: var(--gray-600);
    margin: 0;
}

.empty-state p {
    color: var(--gray-400);
    margin-top: 6px;
}

@media (max-width: 768px) {
    .broadcast-grid {
        grid-template-columns: 1fr;
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar select {
        width: 100%;
        min-width: unset;
    }
    .filter-bar .filter-info {
        margin-left: 0;
        text-align: center;
    }
    .stats-row {
        grid-template-columns: repeat(3, 1fr);
    }
    .broadcast-card .meta {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="welcome-section">
            <div>
                <h1><i class="fas fa-bullhorn"></i> Broadcasts</h1>
                <p class="subtitle">
                    <i class="fas fa-flag"></i> 
                    <?php echo htmlspecialchars($state_name); ?> State - Manage Broadcast Messages
                </p>
            </div>
            <div class="actions">
                <a href="broadcasts-create.php" class="btn-primary-sm">
                    <i class="fas fa-plus"></i> New Broadcast
                </a>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="number total"><?php echo $stats['total']; ?></div>
                <div class="label">Total</div>
            </div>
            <div class="stat-box">
                <div class="number sent"><?php echo $stats['sent'] ?? 0; ?></div>
                <div class="label">Sent</div>
            </div>
            <div class="stat-box">
                <div class="number scheduled"><?php echo $stats['scheduled'] ?? 0; ?></div>
                <div class="label">Scheduled</div>
            </div>
            <div class="stat-box">
                <div class="number draft"><?php echo $stats['draft'] ?? 0; ?></div>
                <div class="label">Draft</div>
            </div>
            <div class="stat-box">
                <div class="number failed"><?php echo $stats['failed'] ?? 0; ?></div>
                <div class="label">Failed</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <select id="statusFilter" onchange="applyFilters()">
                <option value="">All Status</option>
                <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                <option value="sending" <?php echo $status_filter === 'sending' ? 'selected' : ''; ?>>Sending</option>
                <option value="sent" <?php echo $status_filter === 'sent' ? 'selected' : ''; ?>>Sent</option>
                <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>

            <span class="filter-info">
                <i class="fas fa-list"></i> <?php echo count($broadcasts); ?> broadcasts found
            </span>
        </div>

        <!-- Broadcast Grid -->
        <div class="broadcast-grid">
            <?php foreach ($broadcasts as $broadcast): 
                $status_class = $broadcast['status'];
                $target_label = $target_labels[$broadcast['target_audience']] ?? ucfirst($broadcast['target_audience']);
                $send_via = json_decode($broadcast['send_via'], true);
                $channels = is_array($send_via) ? implode(', ', array_map('ucfirst', $send_via)) : 'Email';
            ?>
                <div class="broadcast-card">
                    <div class="card-header">
                        <div class="title">
                            <i class="fas fa-bullhorn"></i>
                            <?php echo htmlspecialchars($broadcast['title']); ?>
                        </div>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <span class="dot"></span>
                            <?php echo ucfirst($broadcast['status']); ?>
                        </span>
                    </div>

                    <div class="message-preview">
                        <?php echo htmlspecialchars(substr($broadcast['message'], 0, 120)) . (strlen($broadcast['message']) > 120 ? '...' : ''); ?>
                    </div>

                    <div class="meta">
                        <div class="meta-item">
                            <span class="label">Target</span>
                            <div class="value"><?php echo $target_label; ?></div>
                        </div>
                        <div class="meta-item">
                            <span class="label">Channel</span>
                            <div class="value"><?php echo $channels; ?></div>
                        </div>
                        <div class="meta-item">
                            <span class="label">Recipients</span>
                            <div class="value"><?php echo number_format($broadcast['total_recipients'] ?? 0); ?></div>
                        </div>
                        <div class="meta-item">
                            <span class="label">Read Rate</span>
                            <div class="value">
                                <?php 
                                    $read_rate = ($broadcast['total_recipients'] ?? 0) > 0 ? 
                                        round(($broadcast['read_count'] / ($broadcast['total_recipients'] ?? 1)) * 100, 1) : 0;
                                    echo $read_rate . '%';
                                ?>
                            </div>
                        </div>
                        <div class="meta-item" style="grid-column: span 2;">
                            <span class="label"><?php echo $broadcast['status'] === 'sent' ? 'Sent At' : ($broadcast['status'] === 'scheduled' ? 'Scheduled At' : 'Created At'); ?></span>
                            <div class="value">
                                <?php 
                                    $date = $broadcast['sent_at'] ?? $broadcast['scheduled_at'] ?? $broadcast['created_at'];
                                    echo date('M j, Y g:i A', strtotime($date));
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="card-actions">
                        <?php if ($broadcast['status'] === 'draft'): ?>
                            <a href="broadcasts-edit.php?id=<?php echo $broadcast['id']; ?>" class="btn-edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="broadcasts-send.php?id=<?php echo $broadcast['id']; ?>" class="btn-send">
                                <i class="fas fa-paper-plane"></i> Send
                            </a>
                            <a href="broadcasts-schedule.php?id=<?php echo $broadcast['id']; ?>" class="btn-schedule">
                                <i class="fas fa-calendar-plus"></i> Schedule
                            </a>
                            <a href="broadcasts-delete.php?id=<?php echo $broadcast['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this broadcast?')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        <?php elseif ($broadcast['status'] === 'scheduled'): ?>
                            <a href="broadcasts-edit.php?id=<?php echo $broadcast['id']; ?>" class="btn-edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="broadcasts-send.php?id=<?php echo $broadcast['id']; ?>" class="btn-send">
                                <i class="fas fa-paper-plane"></i> Send Now
                            </a>
                            <a href="broadcasts-delete.php?id=<?php echo $broadcast['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this broadcast?')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        <?php elseif ($broadcast['status'] === 'sent'): ?>
                            <a href="broadcasts-view.php?id=<?php echo $broadcast['id']; ?>" class="btn-view">
                                <i class="fas fa-eye"></i> View
                            </a>
                        <?php else: ?>
                            <a href="broadcasts-view.php?id=<?php echo $broadcast['id']; ?>" class="btn-view">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <?php if ($broadcast['status'] === 'failed'): ?>
                                <a href="broadcasts-send.php?id=<?php echo $broadcast['id']; ?>" class="btn-send">
                                    <i class="fas fa-redo"></i> Retry
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($broadcasts)): ?>
                <div class="empty-state">
                    <i class="fas fa-bullhorn"></i>
                    <h3>No Broadcasts Found</h3>
                    <p>You haven't created any broadcasts yet.</p>
                    <a href="broadcasts-create.php" class="btn-primary-sm" style="margin-top:12px;">
                        <i class="fas fa-plus"></i> Create Broadcast
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function applyFilters() {
    var status = document.getElementById('statusFilter').value;
    var url = window.location.pathname;
    if (status) url += '?status=' + status;
    window.location.href = url;
}

// Same sidebar scripts as index.php
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