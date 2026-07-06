<?php
// ============================================================
// NATIONAL COORDINATOR - POLLING UNIT RESULTS
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

// Get PU ID from URL
$pu_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($pu_id <= 0) {
    header('Location: monitor-states.php?error=invalid_pu');
    exit();
}

$db = getDB();

// ============================================================
// FETCH PU DATA
// ============================================================
$pu_data = null;
$ward_name = '';
$lga_name = '';
$state_name = '';
$ward_id = 0;

try {
    $stmt = $db->prepare("
        SELECT 
            pu.*,
            w.name as ward_name,
            w.id as ward_id,
            l.name as lga_name,
            s.name as state_name
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        JOIN states s ON l.state_id = s.id
        WHERE pu.id = ?
    ");
    $stmt->execute([$pu_id]);
    $pu_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pu_data) {
        header('Location: monitor-states.php?error=pu_not_found');
        exit();
    }
    
    $ward_name = $pu_data['ward_name'];
    $lga_name = $pu_data['lga_name'];
    $state_name = $pu_data['state_name'];
    $ward_id = $pu_data['ward_id'];
    
} catch (Exception $e) {
    error_log("PU Results Error: " . $e->getMessage());
    header('Location: monitor-states.php?error=database_error');
    exit();
}

// ============================================================
// FETCH RESULTS
// ============================================================
$results = [];

try {
    $stmt = $db->prepare("
        SELECT 
            r.*,
            u.full_name as agent_name,
            u.phone as agent_phone,
            vu.full_name as verified_by_name
        FROM results_ec8a r
        LEFT JOIN users u ON r.agent_id = u.id
        LEFT JOIN users vu ON r.verified_by = vu.id
        WHERE r.tenant_id = ? AND r.pu_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$tenant_id, $pu_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Results fetch error: " . $e->getMessage());
}

// ============================================================
// FETCH STATISTICS
// ============================================================
$stats = [
    'total' => 0,
    'verified' => 0,
    'pending' => 0,
    'flagged' => 0,
    'rejected' => 0,
    'total_votes' => 0,
    'valid_votes' => 0,
    'rejected_votes' => 0
];

foreach ($results as $result) {
    $stats['total']++;
    $status = $result['status'] ?? '';
    if (isset($stats[$status])) {
        $stats[$status]++;
    }
    $stats['total_votes'] += $result['total_votes_cast'] ?? 0;
    $stats['valid_votes'] += $result['valid_votes'] ?? 0;
    $stats['rejected_votes'] += $result['rejected_votes'] ?? 0;
}

// ============================================================
// STATUS COLORS
// ============================================================
$status_colors = [
    'pending' => '#F59E0B',
    'verified' => '#10B981',
    'rejected' => '#EF4444',
    'flagged' => '#8B5CF6',
    'approved' => '#10B981'
];

$status_labels = [
    'pending' => 'Pending',
    'verified' => 'Verified',
    'rejected' => 'Rejected',
    'flagged' => 'Flagged',
    'approved' => 'Approved'
];

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'PU Results';
$page_subtitle = $pu_data['name'] ?? 'Polling Unit';
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
                <a href="pu-dashboard.php?id=<?php echo $pu_id; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($pu_data['name']); ?></a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Results</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-file-alt" style="color:var(--primary);"></i>
                        Polling Unit Results
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-flag-checkered"></i> 
                        <?php echo htmlspecialchars($pu_data['name']); ?> • 
                        <?php echo number_format($stats['total']); ?> results
                    </p>
                    <p style="color:var(--gray-400);font-size:0.75rem;margin:2px 0 0;">
                        <?php echo htmlspecialchars($ward_name); ?> • <?php echo htmlspecialchars($lga_name); ?> • <?php echo htmlspecialchars($state_name); ?>
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="pu-dashboard.php?id=<?php echo $pu_id; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back to PU
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-label">Total Results</div>
                <div class="stat-change">All records</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['verified']); ?></div>
                <div class="stat-label">Verified</div>
                <div class="stat-change up"><i class="fas fa-check"></i> Approved</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($stats['pending']); ?></div>
                <div class="stat-label">Pending</div>
                <div class="stat-change down"><i class="fas fa-hourglass-half"></i> Awaiting</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-flag"></i></div>
                <div class="stat-number"><?php echo number_format($stats['flagged']); ?></div>
                <div class="stat-label">Flagged</div>
                <div class="stat-change down"><i class="fas fa-exclamation-triangle"></i> Review</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_votes']); ?></div>
                <div class="stat-label">Total Votes Cast</div>
                <div class="stat-change">All votes</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pink"><i class="fas fa-check-double"></i></div>
                <div class="stat-number"><?php echo number_format($stats['valid_votes']); ?></div>
                <div class="stat-label">Valid Votes</div>
                <div class="stat-change up"><i class="fas fa-check"></i> Accepted</div>
            </div>
        </div>

        <!-- Results Table -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);">
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0;">
                    <i class="fas fa-list" style="color:var(--primary);margin-right:6px;"></i>
                    Result Records
                </h4>
                <span style="font-size:0.75rem;color:var(--gray-500);"><?php echo count($results); ?> results</span>
            </div>
            
            <?php if (count($results) > 0): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                        <thead style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
                            <tr>
                                <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--gray-600);">Agent</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Votes</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Status</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Verified By</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Submitted</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): 
                                $status_color = $status_colors[$result['status']] ?? '#6B7280';
                                $status_label = $status_labels[$result['status']] ?? ucfirst($result['status']);
                            ?>
                                <tr style="border-bottom:1px solid var(--gray-100);transition:var(--transition);hover:background:var(--gray-50);">
                                    <td style="padding:10px 14px;">
                                        <div style="font-weight:500;font-size:0.85rem;"><?php echo htmlspecialchars($result['agent_name'] ?? 'Unknown'); ?></div>
                                        <div style="font-size:0.65rem;color:var(--gray-400);"><?php echo htmlspecialchars($result['agent_phone'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <div style="font-weight:600;font-size:1rem;"><?php echo number_format($result['total_votes_cast']); ?></div>
                                        <div style="font-size:0.6rem;color:var(--gray-400);">
                                            Valid: <?php echo number_format($result['valid_votes']); ?> • 
                                            Rejected: <?php echo number_format($result['rejected_votes']); ?>
                                        </div>
                                        <?php if (!empty($result['party_votes_json'])): 
                                            $party_votes = json_decode($result['party_votes_json'], true);
                                            if ($party_votes && is_array($party_votes)):
                                        ?>
                                            <div style="font-size:0.6rem;color:var(--gray-500);margin-top:2px;">
                                                <?php 
                                                $display_votes = array_slice($party_votes, 0, 3);
                                                foreach ($display_votes as $party => $votes): 
                                                ?>
                                                    <span style="display:inline-block;margin:0 2px;"><?php echo htmlspecialchars($party); ?>: <?php echo number_format($votes); ?></span>
                                                <?php endforeach; 
                                                if (count($party_votes) > 3): ?>
                                                    <span>+<?php echo count($party_votes) - 3; ?> more</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <span style="display:inline-block;padding:2px 12px;border-radius:12px;font-size:0.65rem;font-weight:600;background:<?php echo $status_color; ?>20;color:<?php echo $status_color; ?>;">
                                            <?php echo $status_label; ?>
                                        </span>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-size:0.75rem;">
                                        <?php if ($result['verified_by']): ?>
                                            <div><?php echo htmlspecialchars($result['verified_by_name'] ?? 'Unknown'); ?></div>
                                            <div style="font-size:0.6rem;color:var(--gray-400);">
                                                <?php echo date('M j, Y g:i A', strtotime($result['verified_at'])); ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-size:0.7rem;color:var(--gray-500);">
                                        <?php echo date('M j, Y', strtotime($result['created_at'])); ?>
                                        <div style="font-size:0.6rem;color:var(--gray-400);">
                                            <?php echo date('g:i A', strtotime($result['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
                                            <a href="result-view.php?id=<?php echo $result['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:6px;background:var(--primary);color:white;text-decoration:none;font-size:0.65rem;" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($result['status'] === 'pending'): ?>
                                                <a href="result-verify.php?id=<?php echo $result['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:6px;background:#10B981;color:white;text-decoration:none;font-size:0.65rem;" title="Verify">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($result['status'] === 'flagged' || $result['status'] === 'pending'): ?>
                                                <a href="result-flag.php?id=<?php echo $result['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:6px;background:#EF4444;color:white;text-decoration:none;font-size:0.65rem;" title="Flag">
                                                    <i class="fas fa-flag"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($result['photo_url'])): ?>
                                                <a href="<?php echo $result['photo_url']; ?>" target="_blank" class="btn-sm" style="padding:3px 10px;border-radius:6px;background:#8B5CF6;color:white;text-decoration:none;font-size:0.65rem;" title="View Photo">
                                                    <i class="fas fa-image"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="padding:60px 20px;text-align:center;color:var(--gray-500);">
                    <i class="fas fa-file-alt" style="font-size:3rem;display:block;margin-bottom:12px;color:var(--gray-300);"></i>
                    <h3 style="font-size:1.1rem;font-weight:600;color:var(--gray-600);margin:0 0 8px;">No Results Found</h3>
                    <p style="font-size:0.85rem;color:var(--gray-400);margin:0;">No results have been submitted for this polling unit yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
.btn-sm:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.btn-secondary:hover { background: var(--gray-200); transform: translateY(-1px); }

.stat-icon.teal { background: #CCFBF1; color: #0D9488; }
.stat-icon.pink { background: #FCE7F3; color: #DB2777; }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    table { font-size: 0.7rem; }
    th, td { padding: 6px 8px !important; }
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