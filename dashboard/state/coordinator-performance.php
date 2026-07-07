<?php
// ============================================================
// STATE COORDINATOR - COORDINATOR PERFORMANCE
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

// Only state coordinator can access
if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$user_email = SessionManager::get('user_email');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

// Get parameters
$lga_id = isset($_GET['lga']) ? intval($_GET['lga']) : 0;
$period = isset($_GET['period']) ? $_GET['period'] : 'all';

$db = getDB();

// ============================================================
// FETCH LGA NAME
// ============================================================
$lga_name = '';
if ($lga_id > 0) {
    try {
        $stmt = $db->prepare("SELECT name FROM lgas WHERE id = ? AND state_id = ?");
        $stmt->execute([$lga_id, $state_id]);
        $lga_name = $stmt->fetchColumn() ?: '';
    } catch (Exception $e) {
        $lga_name = '';
    }
}

// ============================================================
// FETCH PERFORMANCE DATA
// ============================================================
$performance = [
    'coordinators' => [],
    'total_coordinators' => 0,
    'active_coordinators' => 0,
    'total_submissions' => 0,
    'total_verified' => 0,
    'total_checkins' => 0,
    'total_incidents' => 0,
    'submission_rate' => 0,
    'verification_rate' => 0,
    'top_performers' => []
];

try {
    // Build query for coordinators
    $where_clauses = ['u.tenant_id = ?', 'r.level IN ("lga", "ward", "pu_agent")', '(u.deleted_at IS NULL OR u.deleted_at = "0000-00-00 00:00:00")'];
    $params = [$tenant_id];
    
    if ($lga_id > 0) {
        $where_clauses[] = 'u.jurisdiction_id IN (SELECT id FROM lgas WHERE state_id = ?)';
        $params[] = $state_id;
        $where_clauses[] = 'u.jurisdiction_id = ?';
        $params[] = $lga_id;
    } else {
        $where_clauses[] = 'u.jurisdiction_id IN (SELECT id FROM lgas WHERE state_id = ?)';
        $params[] = $state_id;
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    
    // Fetch coordinators with stats
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.email,
            u.phone,
            u.status,
            r.name as role_name,
            r.level as role_level,
            CASE 
                WHEN u.jurisdiction_type = 'lga' THEN (SELECT name FROM lgas WHERE id = u.jurisdiction_id)
                WHEN u.jurisdiction_type = 'ward' THEN (SELECT name FROM wards WHERE id = u.jurisdiction_id)
                WHEN u.jurisdiction_type = 'pu' THEN (SELECT name FROM polling_units WHERE id = u.jurisdiction_id)
                ELSE 'Unknown'
            END as jurisdiction_name,
            (SELECT COUNT(*) FROM results_ec8a r2 WHERE r2.agent_id = u.id AND r2.tenant_id = ?) as submissions,
            (SELECT COUNT(*) FROM results_ec8a r2 WHERE r2.agent_id = u.id AND r2.tenant_id = ? AND r2.status = 'verified') as verified_submissions,
            (SELECT COUNT(*) FROM agent_checkins ac WHERE ac.agent_id = u.id AND ac.tenant_id = ?) as checkins,
            (SELECT COUNT(*) FROM incidents i WHERE i.reporter_id = u.id AND i.tenant_id = ?) as incidents,
            (SELECT COUNT(*) FROM results_ec8a r2 WHERE r2.agent_id = u.id AND r2.tenant_id = ? AND r2.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as weekly_submissions,
            (SELECT COUNT(*) FROM agent_checkins ac WHERE ac.agent_id = u.id AND ac.tenant_id = ? AND ac.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as weekly_checkins,
            u.last_login_at,
            u.created_at
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE $where_sql
        ORDER BY u.full_name ASC
    ");
    
    $query_params = array_merge([$tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id], $params);
    $stmt->execute($query_params);
    $performance['coordinators'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    foreach ($performance['coordinators'] as $coord) {
        $performance['total_coordinators']++;
        if ($coord['status'] === 'active') $performance['active_coordinators']++;
        $performance['total_submissions'] += $coord['submissions'];
        $performance['total_verified'] += $coord['verified_submissions'];
        $performance['total_checkins'] += $coord['checkins'];
        $performance['total_incidents'] += $coord['incidents'];
    }
    
    // Calculate rates
    $performance['submission_rate'] = $performance['total_coordinators'] > 0 
        ? round(($performance['total_submissions'] / $performance['total_coordinators']) * 100) 
        : 0;
    $performance['verification_rate'] = $performance['total_submissions'] > 0 
        ? round(($performance['total_verified'] / $performance['total_submissions']) * 100) 
        : 0;
    
    // Top performers (by verified submissions)
    $performance['top_performers'] = $performance['coordinators'];
    usort($performance['top_performers'], function($a, $b) {
        return $b['verified_submissions'] - $a['verified_submissions'];
    });
    $performance['top_performers'] = array_slice($performance['top_performers'], 0, 10);
    
} catch (Exception $e) {
    error_log("Performance Error: " . $e->getMessage());
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Coordinator Performance';
$page_subtitle = $lga_name ? "LGA: $lga_name" : 'State Overview';
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Breadcrumb -->
        <div class="welcome-section">
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <a href="index.php" style="text-decoration:none;color:var(--gray-500);">Dashboard</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Coordinator Performance</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-chart-bar" style="color:var(--primary);"></i>
                        Coordinator Performance
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-user-tie"></i> 
                        <?php echo number_format($performance['total_coordinators']); ?> coordinators • 
                        <?php echo number_format($performance['total_submissions']); ?> submissions
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="reports.php?type=performance&state=<?php echo $state_id; ?>" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-file-pdf"></i> Export Report
                    </a>
                    <a href="monitor-lgas.php" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format($performance['total_coordinators']); ?></div>
                <div class="stat-label">Total Coordinators</div>
                <div class="stat-change"><i class="fas fa-check-circle"></i> <?php echo $performance['active_coordinators']; ?> active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-double"></i></div>
                <div class="stat-number"><?php echo number_format($performance['total_submissions']); ?></div>
                <div class="stat-label">Total Submissions</div>
                <div class="stat-change"><i class="fas fa-upload"></i> Results</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($performance['total_verified']); ?></div>
                <div class="stat-label">Verified Submissions</div>
                <div class="stat-change up"><i class="fas fa-check"></i> <?php echo $performance['verification_rate']; ?>% rate</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-sign-in-alt"></i></div>
                <div class="stat-number"><?php echo number_format($performance['total_checkins']); ?></div>
                <div class="stat-label">Total Check-ins</div>
                <div class="stat-change"><i class="fas fa-clock"></i> Attendance</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($performance['total_incidents']); ?></div>
                <div class="stat-label">Incidents Reported</div>
                <div class="stat-change down"><i class="fas fa-flag"></i> Total</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-percent"></i></div>
                <div class="stat-number"><?php echo $performance['submission_rate']; ?>%</div>
                <div class="stat-label">Submission Rate</div>
                <div class="stat-change <?php echo $performance['submission_rate'] >= 70 ? 'up' : 'down'; ?>">
                    Per coordinator average
                </div>
            </div>
        </div>

        <!-- Top Performers -->
        <div style="background:white;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h4 style="font-size:0.85rem;font-weight:600;margin:0;">
                    <i class="fas fa-trophy" style="color:var(--warning);margin-right:6px;"></i>
                    Top Performers
                </h4>
                <span style="font-size:0.7rem;color:var(--gray-400);">By verified submissions</span>
            </div>
            <?php if (count($performance['top_performers']) > 0): ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;">
                    <?php 
                    $medal_colors = ['#FFD700', '#C0C0C0', '#CD7F32', '#3B82F6', '#3B82F6', '#3B82F6', '#3B82F6', '#3B82F6', '#3B82F6', '#3B82F6'];
                    $index = 0;
                    foreach ($performance['top_performers'] as $coord): 
                        if ($coord['verified_submissions'] == 0 && $coord['submissions'] == 0) continue;
                        $medal = $medal_colors[$index] ?? '#3B82F6';
                        $rank = $index + 1;
                    ?>
                        <div style="display:flex;align-items:center;gap:12px;padding:8px 12px;background:var(--gray-50);border-radius:8px;border:1px solid var(--gray-200);">
                            <div style="width:32px;height:32px;border-radius:50%;background:<?php echo $medal; ?>;color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.8rem;flex-shrink:0;">
                                <?php echo $rank; ?>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-weight:500;font-size:0.8rem;"><?php echo htmlspecialchars($coord['full_name'] ?? 'Unknown'); ?></div>
                                <div style="font-size:0.65rem;color:var(--gray-400);">
                                    <?php echo htmlspecialchars($coord['role_name'] ?? ''); ?> • 
                                    <?php echo htmlspecialchars($coord['jurisdiction_name'] ?? ''); ?>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-weight:600;font-size:0.9rem;color:#10B981;"><?php echo number_format($coord['verified_submissions']); ?></div>
                                <div style="font-size:0.55rem;color:var(--gray-400);">verified</div>
                            </div>
                        </div>
                    <?php 
                        $index++;
                    endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color:var(--gray-400);text-align:center;padding:16px 0;">No performance data available</p>
            <?php endif; ?>
        </div>

        <!-- Coordinators Table -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);">
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0;">
                    <i class="fas fa-list" style="color:var(--primary);margin-right:6px;"></i>
                    All Coordinators
                </h4>
                <span style="font-size:0.75rem;color:var(--gray-500);"><?php echo count($performance['coordinators']); ?> coordinators</span>
            </div>
            
            <?php if (count($performance['coordinators']) > 0): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.75rem;">
                        <thead style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
                            <tr>
                                <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--gray-600);">Coordinator</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Role</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Status</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Submissions</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Verified</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Check-ins</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Incidents</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Weekly</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Last Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($performance['coordinators'] as $coord): ?>
                                <tr style="border-bottom:1px solid var(--gray-100);transition:var(--transition);hover:background:var(--gray-50);">
                                    <td style="padding:8px 12px;">
                                        <div style="font-weight:500;font-size:0.8rem;"><?php echo htmlspecialchars($coord['full_name'] ?? 'Unknown'); ?></div>
                                        <div style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($coord['email'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;font-size:0.7rem;">
                                        <?php echo htmlspecialchars($coord['role_name'] ?? ''); ?>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;">
                                        <span style="display:inline-block;padding:2px 8px;border-radius:8px;font-size:0.6rem;font-weight:600;background:<?php echo ($coord['status'] ?? '') === 'active' ? '#D1FAE5' : '#FEE2E2'; ?>;color:<?php echo ($coord['status'] ?? '') === 'active' ? '#065F46' : '#991B1B'; ?>;">
                                            <?php echo ucfirst($coord['status'] ?? 'Unknown'); ?>
                                        </span>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;font-weight:600;color:var(--primary);">
                                        <?php echo number_format($coord['submissions']); ?>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;font-weight:600;color:#10B981;">
                                        <?php echo number_format($coord['verified_submissions']); ?>
                                        <div style="font-size:0.55rem;color:var(--gray-400);">
                                            <?php echo $coord['submissions'] > 0 ? round(($coord['verified_submissions'] / $coord['submissions']) * 100) : 0; ?>%
                                        </div>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;font-weight:600;color:#8B5CF6;">
                                        <?php echo number_format($coord['checkins']); ?>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;font-weight:600;">
                                        <?php if ($coord['incidents'] > 0): ?>
                                            <span style="color:var(--danger);"><?php echo number_format($coord['incidents']); ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;font-size:0.7rem;">
                                        <div style="font-weight:600;color:<?php echo ($coord['weekly_submissions'] + $coord['weekly_checkins']) > 0 ? '#10B981' : '#6B7280'; ?>;">
                                            <?php echo number_format($coord['weekly_submissions']); ?> / <?php echo number_format($coord['weekly_checkins']); ?>
                                        </div>
                                        <div style="font-size:0.55rem;color:var(--gray-400);">Sub / Check-in</div>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;font-size:0.65rem;color:var(--gray-500);">
                                        <?php if ($coord['last_login_at']): ?>
                                            <?php echo date('M j, Y', strtotime($coord['last_login_at'])); ?>
                                            <div style="font-size:0.55rem;color:var(--gray-400);">
                                                <?php echo date('g:i A', strtotime($coord['last_login_at'])); ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">Never</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="padding:40px 20px;text-align:center;color:var(--gray-500);">
                    <i class="fas fa-user-tie" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                    <p style="font-size:0.85rem;">No coordinator data available</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
.stat-icon.teal { background: #CCFBF1; color: #0D9488; }
.btn-secondary:hover { background: var(--gray-200); transform: translateY(-1px); }
.btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3); }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    table { font-size: 0.65rem; }
    th, td { padding: 4px 6px !important; }
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