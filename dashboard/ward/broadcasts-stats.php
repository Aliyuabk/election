<?php
// ============================================================
// WARD COORDINATOR - BROADCAST STATISTICS
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
            u.email as sender_email
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

$page_title = 'Broadcast Statistics';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.stats-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.stats-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.stats-header h2 i {
    color: var(--primary);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.stat-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    text-align: center;
}
.stat-card .number {
    font-size: 2rem;
    font-weight: 700;
}
.stat-card .number.blue { color: #3B82F6; }
.stat-card .number.green { color: #10B981; }
.stat-card .number.orange { color: #F59E0B; }
.stat-card .number.purple { color: #8B5CF6; }
.stat-card .label {
    font-size: 0.75rem;
    color: var(--gray-500);
    font-weight: 500;
}
.stat-card .sub {
    font-size: 0.65rem;
    color: var(--gray-400);
    margin-top: 2px;
}

.broadcast-detail {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
    margin-bottom: 20px;
}
.broadcast-detail .detail-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 8px;
}
.broadcast-detail .detail-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 0.8rem;
    color: var(--gray-500);
}
.broadcast-detail .detail-meta i {
    width: 16px;
}

.chart-container {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
    margin-bottom: 20px;
}
.chart-container .chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.chart-container .chart-header h3 {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0;
}
.chart-container .chart-wrapper {
    height: 250px;
    position: relative;
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

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="stats-header">
            <div>
                <h2><i class="fas fa-chart-bar"></i> Broadcast Statistics</h2>
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
            <!-- Broadcast Info -->
            <div class="broadcast-detail">
                <div class="detail-title">
                    <?php echo htmlspecialchars($broadcast['title']); ?>
                    <span class="badge <?php echo $broadcast['status']; ?>" style="margin-left:12px;font-size:0.7rem;">
                        <?php echo ucfirst($broadcast['status']); ?>
                    </span>
                </div>
                <div class="detail-meta">
                    <span><i class="fas fa-user"></i> From: <?php echo htmlspecialchars($broadcast['sender_name'] ?? 'Unknown'); ?></span>
                    <span><i class="fas fa-clock"></i> Created: <?php echo date('M d, Y H:i', strtotime($broadcast['created_at'])); ?></span>
                    <?php if ($broadcast['sent_at']): ?>
                        <span><i class="fas fa-check-circle" style="color:#10B981;"></i> Sent: <?php echo date('M d, Y H:i', strtotime($broadcast['sent_at'])); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="number blue"><?php echo number_format($broadcast['total_recipients'] ?? 0); ?></div>
                    <div class="label">Total Recipients</div>
                    <div class="sub">Target audience size</div>
                </div>
                <div class="stat-card">
                    <div class="number green"><?php echo number_format($broadcast['read_count'] ?? 0); ?></div>
                    <div class="label">Read Count</div>
                    <div class="sub">Total views</div>
                </div>
                <div class="stat-card">
                    <div class="number purple"><?php echo number_format($broadcast['total_recipients'] ? round(($broadcast['read_count'] / $broadcast['total_recipients']) * 100, 1) : 0); ?>%</div>
                    <div class="label">Read Rate</div>
                    <div class="sub">of total recipients</div>
                </div>
                <div class="stat-card">
                    <div class="number orange">
                        <?php 
                        $channels = json_decode($broadcast['send_via'] ?? '[]', true);
                        echo !empty($channels) ? count($channels) : 1;
                        ?>
                    </div>
                    <div class="label">Channels Used</div>
                    <div class="sub"><?php echo !empty($channels) ? implode(', ', array_map('ucfirst', $channels)) : 'Email'; ?></div>
                </div>
            </div>

            <!-- Chart -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-pie" style="color:var(--primary);"></i> Audience Breakdown</h3>
                    <span style="font-size:0.7rem;color:var(--gray-400);">Target: <?php echo ucfirst(str_replace('_', ' ', $broadcast['target_audience'])); ?></span>
                </div>
                <div class="chart-wrapper">
                    <canvas id="audienceChart"></canvas>
                </div>
            </div>

            <!-- Additional Info -->
            <div style="background:white;border-radius:var(--radius);border:1px solid var(--gray-200);padding:16px;">
                <h4 style="font-size:0.9rem;margin:0 0 8px;">Message Preview</h4>
                <div style="font-size:0.85rem;color:var(--gray-600);padding:12px;background:var(--gray-50);border-radius:6px;white-space:pre-wrap;max-height:200px;overflow-y:auto;">
                    <?php echo nl2br(htmlspecialchars($broadcast['message'])); ?>
                </div>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if ($broadcast): ?>
// Audience Chart
const ctx = document.getElementById('audienceChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Read', 'Not Read'],
        datasets: [{
            data: [
                <?php echo $broadcast['read_count'] ?? 0; ?>,
                <?php echo max(0, ($broadcast['total_recipients'] ?? 0) - ($broadcast['read_count'] ?? 0)); ?>
            ],
            backgroundColor: ['#10B981', '#E5E7EB'],
            borderWidth: 2,
            borderColor: 'white'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    pointStyle: 'circle',
                    padding: 16,
                    font: { size: 12 }
                }
            }
        },
        cutout: '65%'
    }
});
<?php endif; ?>

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