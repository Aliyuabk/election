<?php
// ============================================================
// WARD COORDINATOR - EC8B DETAILS
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
// GET EC8B ID
// ============================================================
$ec8b_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ec8b_id <= 0) {
    header('Location: ec8b-history.php');
    exit();
}

// ============================================================
// FETCH EC8B DETAILS
// ============================================================
$ec8b = null;
$error_message = '';

try {
    $stmt = $db->prepare("
        SELECT 
            e.*,
            u.full_name as coordinator_name,
            u.user_code as coordinator_code,
            u.email as coordinator_email,
            u.phone as coordinator_phone,
            w.name as ward_name,
            l.name as lga_name,
            s.name as state_name,
            verified_user.full_name as verified_by_name,
            verified_user.user_code as verified_by_code
        FROM results_ec8b e
        JOIN users u ON e.coordinator_id = u.id
        JOIN wards w ON e.ward_id = w.id
        JOIN lgas l ON e.lga_id = l.id
        JOIN states s ON e.state_id = s.id
        LEFT JOIN users verified_user ON e.verified_by = verified_user.id
        WHERE e.id = ? AND e.tenant_id = ?
    ");
    $stmt->execute([$ec8b_id, $tenant_id]);
    $ec8b = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ec8b) {
        header('Location: ec8b-history.php?error=notfound');
        exit();
    }
    
    // Check if EC8B belongs to this ward
    if ($ec8b['ward_id'] != $ward_id) {
        header('Location: ec8b-history.php?error=unauthorized');
        exit();
    }
    
} catch (Exception $e) {
    error_log("Error fetching EC8B details: " . $e->getMessage());
    header('Location: ec8b-history.php?error=db');
    exit();
}

// ============================================================
// PARSE DATA
// ============================================================
$party_votes = json_decode($ec8b['party_votes_json'] ?? '{}', true);
$calculated_total = json_decode($ec8b['calculated_total_json'] ?? '{}', true);
$total_valid = array_sum($party_votes);

$page_title = 'EC8B Details';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.detail-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.detail-header h2 i {
    color: var(--primary);
}

.detail-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 16px;
}
.detail-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px;
    margin-bottom: 16px;
}
.detail-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
}
.detail-card .card-header h3 {
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0;
}

.info-row {
    display: flex;
    padding: 6px 0;
    font-size: 0.85rem;
    border-bottom: 1px solid var(--gray-100);
}
.info-row:last-child {
    border-bottom: none;
}
.info-row .label {
    width: 140px;
    color: var(--gray-500);
    font-weight: 500;
    flex-shrink: 0;
}
.info-row .value {
    flex: 1;
    color: var(--gray-800);
}

.party-votes-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}
.party-vote-item {
    display: flex;
    justify-content: space-between;
    padding: 6px 12px;
    background: var(--gray-50);
    border-radius: 4px;
    font-size: 0.85rem;
}
.party-vote-item .party {
    font-weight: 500;
}
.party-vote-item .votes {
    font-weight: 600;
}
.party-vote-item.total {
    background: #EFF6FF;
    font-weight: 700;
    grid-column: 1/-1;
}
.party-vote-item.mismatch {
    background: #FEF2F2;
    color: #991B1B;
}

.status-badge {
    font-size: 0.7rem;
    padding: 4px 14px;
    border-radius: 20px;
    font-weight: 500;
    display: inline-block;
}
.status-badge.pending { background: #FEF3C7; color: #92400E; }
.status-badge.verified { background: #D1FAE5; color: #065F46; }
.status-badge.rejected { background: #FEE2E2; color: #991B1B; }
.status-badge.flagged { background: #FEF3C7; color: #92400E; }

.actions-section {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
}
.actions-section .btn-sm {
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
.actions-section .btn-sm.verify { background: #D1FAE5; color: #065F46; }
.actions-section .btn-sm.edit { background: #FEF3C7; color: #92400E; }
.actions-section .btn-sm.back { background: #E5E7EB; color: #374151; }

@media (max-width: 1024px) {
    .detail-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .info-row {
        flex-direction: column;
        padding: 8px 0;
    }
    .info-row .label {
        width: 100%;
        font-size: 0.75rem;
    }
    .party-votes-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="detail-header">
            <div>
                <h2><i class="fas fa-file-alt"></i> EC8B Details</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ec8b['ward_name'] ?? ''); ?> Ward • Form #<?php echo $ec8b_id; ?>
                </p>
            </div>
            <div>
                <span class="status-badge <?php echo $ec8b['status'] ?? 'pending'; ?>">
                    <i class="fas fa-circle" style="font-size:0.4rem;"></i>
                    <?php echo ucfirst($ec8b['status'] ?? 'Pending'); ?>
                </span>
            </div>
        </div>

        <div class="detail-grid">
            <!-- Left Column -->
            <div>
                <!-- Result Information -->
                <div class="detail-card">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> Form Information</h3>
                    </div>
                    <div class="info-row">
                        <span class="label">Form ID</span>
                        <span class="value">#<?php echo $ec8b['id']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Ward</span>
                        <span class="value"><?php echo htmlspecialchars($ec8b['ward_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">LGA</span>
                        <span class="value"><?php echo htmlspecialchars($ec8b['lga_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">State</span>
                        <span class="value"><?php echo htmlspecialchars($ec8b['state_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Coordinator</span>
                        <span class="value">
                            <?php echo htmlspecialchars($ec8b['coordinator_name']); ?>
                            (<?php echo htmlspecialchars($ec8b['coordinator_code']); ?>)
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="label">Created At</span>
                        <span class="value"><?php echo date('M d, Y H:i:s', strtotime($ec8b['created_at'])); ?></span>
                    </div>
                    <?php if ($ec8b['verified_by_name']): ?>
                        <div class="info-row">
                            <span class="label">Verified By</span>
                            <span class="value">
                                <?php echo htmlspecialchars($ec8b['verified_by_name']); ?>
                                (<?php echo htmlspecialchars($ec8b['verified_by_code']); ?>)
                            </span>
                        </div>
                    <?php endif; ?>
                    <?php if ($ec8b['mismatch_alert'] ?? 0): ?>
                        <div class="info-row" style="color:#EF4444;background:#FEF2F2;padding:8px 12px;border-radius:4px;margin-top:8px;">
                            <span class="label">⚠️ Mismatch Alert</span>
                            <span class="value">Calculated total does not match entered total</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Party Votes -->
                <div class="detail-card">
                    <div class="card-header">
                        <h3><i class="fas fa-vote-yea"></i> Party Votes</h3>
                    </div>
                    <div class="party-votes-grid">
                        <?php if (!empty($party_votes)): ?>
                            <?php foreach ($party_votes as $party => $votes): ?>
                                <div class="party-vote-item">
                                    <span class="party"><?php echo htmlspecialchars($party); ?></span>
                                    <span class="votes"><?php echo number_format($votes); ?></span>
                                </div>
                            <?php endforeach; ?>
                            <div class="party-vote-item total">
                                <span class="party">Total Valid Votes</span>
                                <span class="votes"><?php echo number_format($total_valid); ?></span>
                            </div>
                            <div class="party-vote-item total" style="background:#FEF2F2;">
                                <span class="party">Rejected Votes</span>
                                <span class="votes"><?php echo number_format($ec8b['rejected_votes'] ?? 0); ?></span>
                            </div>
                            <div class="party-vote-item total" style="background:#F5F3FF;">
                                <span class="party">Total Votes</span>
                                <span class="votes"><?php echo number_format($ec8b['total_votes'] ?? 0); ?></span>
                            </div>
                            <?php if (!empty($calculated_total)): ?>
                                <div class="party-vote-item total" style="background:#EFF6FF;grid-column:1/-1;">
                                    <span class="party">Calculated Total (Auto)</span>
                                    <span class="votes"><?php echo number_format($calculated_total['total_votes'] ?? 0); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="grid-column:1/-1;text-align:center;color:var(--gray-400);padding:12px;">
                                No party votes recorded
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Coordinator Information -->
                <div class="detail-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user"></i> Coordinator</h3>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                        <div style="width:50px;height:50px;border-radius:50%;background:var(--gray-200);display:flex;align-items:center;justify-content:center;font-size:1.2rem;font-weight:700;color:var(--gray-600);">
                            <?php echo strtoupper(substr($ec8b['coordinator_name'] ?? 'U', 0, 2)); ?>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:0.95rem;"><?php echo htmlspecialchars($ec8b['coordinator_name']); ?></div>
                            <div style="font-size:0.75rem;color:var(--gray-500);"><?php echo htmlspecialchars($ec8b['coordinator_code']); ?></div>
                        </div>
                    </div>
                    <div class="info-row">
                        <span class="label">Email</span>
                        <span class="value"><?php echo htmlspecialchars($ec8b['coordinator_email'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Phone</span>
                        <span class="value"><?php echo htmlspecialchars($ec8b['coordinator_phone'] ?? 'N/A'); ?></span>
                    </div>
                </div>

                <!-- Actions -->
                <?php if ($ec8b['status'] === 'pending'): ?>
                    <div class="detail-card">
                        <div class="card-header">
                            <h3><i class="fas fa-tasks"></i> Actions</h3>
                        </div>
                        <div class="actions-section">
                            <a href="verify-ec8b.php?id=<?php echo $ec8b_id; ?>" class="btn-sm verify">
                                <i class="fas fa-check"></i> Verify
                            </a>
                            <a href="ec8b-edit.php?id=<?php echo $ec8b_id; ?>" class="btn-sm edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Navigation -->
                <div class="detail-card">
                    <div class="card-header">
                        <h3><i class="fas fa-arrow-left"></i> Navigation</h3>
                    </div>
                    <div class="actions-section">
                        <a href="ec8b-history.php" class="btn-sm back" style="width:100%;justify-content:center;">
                            <i class="fas fa-arrow-left"></i> Back to History
                        </a>
                        <?php if ($ec8b['status'] !== 'pending'): ?>
                            <a href="ec8b-history.php?status=<?php echo $ec8b['status']; ?>" class="btn-sm back" style="width:100%;justify-content:center;background:#EFF6FF;color:#3B82F6;">
                                <i class="fas fa-list"></i> View All <?php echo ucfirst($ec8b['status']); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
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