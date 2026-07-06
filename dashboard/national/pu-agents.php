<?php
// ============================================================
// NATIONAL COORDINATOR - VIEW POLLING UNIT AGENTS
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

// Get parameters
$pu_id = isset($_GET['pu']) ? intval($_GET['pu']) : 0;
$ward_id = isset($_GET['ward']) ? intval($_GET['ward']) : 0;
$lga_id = isset($_GET['lga']) ? intval($_GET['lga']) : 0;

$db = getDB();

// ============================================================
// FETCH LOCATION DATA
// ============================================================
$location_name = '';
$back_url = 'monitor-states.php';
$ward_name = '';
$lga_name = '';
$state_name = '';

if ($pu_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT pu.name as pu_name, w.name as ward_name, l.name as lga_name, s.name as state_name
            FROM polling_units pu
            JOIN wards w ON pu.ward_id = w.id
            JOIN lgas l ON w.lga_id = l.id
            JOIN states s ON l.state_id = s.id
            WHERE pu.id = ?
        ");
        $stmt->execute([$pu_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $location_name = $result['pu_name'];
            $ward_name = $result['ward_name'];
            $lga_name = $result['lga_name'];
            $state_name = $result['state_name'];
            $back_url = "pu-dashboard.php?id=$pu_id";
        }
    } catch (Exception $e) {
        error_log("PU fetch error: " . $e->getMessage());
    }
} elseif ($ward_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT w.name as ward_name, l.name as lga_name, s.name as state_name
            FROM wards w
            JOIN lgas l ON w.lga_id = l.id
            JOIN states s ON l.state_id = s.id
            WHERE w.id = ?
        ");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $location_name = $result['ward_name'];
            $ward_name = $result['ward_name'];
            $lga_name = $result['lga_name'];
            $state_name = $result['state_name'];
            $back_url = "ward-dashboard.php?id=$ward_id";
        }
    } catch (Exception $e) {
        error_log("Ward fetch error: " . $e->getMessage());
    }
}

// ============================================================
// FETCH PU AGENTS
// ============================================================
$agents = [];
$total_agents = 0;

try {
    $where_clauses = ['u.tenant_id = ?', 'r.level = "pu_agent"', 'u.status = "active"'];
    $params = [$tenant_id];
    
    if ($pu_id > 0) {
        $where_clauses[] = 'u.jurisdiction_id = ?';
        $params[] = $pu_id;
    } elseif ($ward_id > 0) {
        $where_clauses[] = 'u.jurisdiction_id IN (SELECT id FROM polling_units WHERE ward_id = ?)';
        $params[] = $ward_id;
    } elseif ($lga_id > 0) {
        $where_clauses[] = 'u.jurisdiction_id IN (SELECT id FROM polling_units WHERE ward_id IN (SELECT id FROM wards WHERE lga_id = ?))';
        $params[] = $lga_id;
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.user_code,
            u.first_name,
            u.last_name,
            u.full_name,
            u.email,
            u.phone,
            u.status,
            u.last_login_at,
            u.created_at,
            r.name as role_name,
            r.level as role_level,
            pu.name as pu_name,
            pu.code as pu_code,
            w.name as ward_name,
            l.name as lga_name,
            s.name as state_name,
            (SELECT COUNT(*) FROM results_ec8a r2 WHERE r2.agent_id = u.id) as submissions,
            (SELECT COUNT(*) FROM agent_checkins ac WHERE ac.agent_id = u.id) as checkins,
            (SELECT COUNT(*) FROM incidents i WHERE i.reporter_id = u.id) as incidents
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN polling_units pu ON u.jurisdiction_id = pu.id
        LEFT JOIN wards w ON pu.ward_id = w.id
        LEFT JOIN lgas l ON w.lga_id = l.id
        LEFT JOIN states s ON l.state_id = s.id
        WHERE $where_sql
        AND (u.deleted_at IS NULL OR u.deleted_at = '0000-00-00 00:00:00')
        ORDER BY u.full_name ASC
    ");
    $stmt->execute($params);
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_agents = count($agents);
    
} catch (Exception $e) {
    error_log("PU Agents Error: " . $e->getMessage());
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Polling Unit Agents';
$page_subtitle = $location_name ? "Location: $location_name" : 'All Agents';
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
                <a href="<?php echo $back_url; ?>" style="text-decoration:none;color:var(--gray-500);">
                    <?php echo htmlspecialchars($location_name ?: 'Back'); ?>
                </a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">PU Agents</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        Polling Unit Agents
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-users"></i> 
                        <?php echo number_format($total_agents); ?> active agents
                        <?php if ($location_name): ?>
                            • <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($location_name); ?>
                        <?php endif; ?>
                    </p>
                    <?php if ($ward_name): ?>
                        <p style="color:var(--gray-400);font-size:0.75rem;margin:2px 0 0;">
                            <?php echo htmlspecialchars($ward_name); ?> • <?php echo htmlspecialchars($lga_name); ?> • <?php echo htmlspecialchars($state_name); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="coordinators-create.php?<?php echo $pu_id > 0 ? 'pu=' . $pu_id : ($ward_id > 0 ? 'ward=' . $ward_id : ''); ?>&level=pu_agent" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-user-plus"></i> Add Agent
                    </a>
                    <a href="<?php echo $back_url; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($total_agents); ?></div>
                <div class="stat-label">Total Agents</div>
                <div class="stat-change">Active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number">
                    <?php 
                    $submissions = 0;
                    foreach ($agents as $agent) {
                        $submissions += $agent['submissions'] ?? 0;
                    }
                    echo number_format($submissions);
                    ?>
                </div>
                <div class="stat-label">Total Submissions</div>
                <div class="stat-change up"><i class="fas fa-upload"></i> Results</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-sign-in-alt"></i></div>
                <div class="stat-number">
                    <?php 
                    $checkins = 0;
                    foreach ($agents as $agent) {
                        $checkins += $agent['checkins'] ?? 0;
                    }
                    echo number_format($checkins);
                    ?>
                </div>
                <div class="stat-label">Total Check-ins</div>
                <div class="stat-change"><i class="fas fa-clock"></i> Attendance</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number">
                    <?php 
                    $incidents = 0;
                    foreach ($agents as $agent) {
                        $incidents += $agent['incidents'] ?? 0;
                    }
                    echo number_format($incidents);
                    ?>
                </div>
                <div class="stat-label">Incidents Reported</div>
                <div class="stat-change down"><i class="fas fa-flag"></i> Total</div>
            </div>
        </div>

        <!-- Agents List -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);">
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0;">
                    <i class="fas fa-user-tie" style="color:var(--primary);margin-right:6px;"></i>
                    Agent List
                </h4>
                <span style="font-size:0.75rem;color:var(--gray-500);"><?php echo number_format($total_agents); ?> agents</span>
            </div>
            
            <?php if (count($agents) > 0): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                        <thead style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
                            <tr>
                                <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--gray-600);">Agent</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">PU</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Submissions</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Check-ins</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Incidents</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Last Login</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agents as $agent): ?>
                                <tr style="border-bottom:1px solid var(--gray-100);transition:var(--transition);hover:background:var(--gray-50);">
                                    <td style="padding:10px 14px;">
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <div style="width:36px;height:36px;border-radius:50%;background:var(--secondary);color:white;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:0.8rem;flex-shrink:0;">
                                                <?php echo strtoupper(substr($agent['first_name'] ?? 'U', 0, 1) . substr($agent['last_name'] ?? 'N', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:500;font-size:0.85rem;"><?php echo htmlspecialchars($agent['full_name'] ?? 'Unknown'); ?></div>
                                                <div style="font-size:0.65rem;color:var(--gray-400);">
                                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($agent['email'] ?? 'N/A'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-size:0.75rem;">
                                        <?php if (!empty($agent['pu_name'])): ?>
                                            <div style="font-weight:500;"><?php echo htmlspecialchars($agent['pu_name']); ?></div>
                                            <div style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($agent['pu_code'] ?? ''); ?></div>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-weight:600;color:var(--primary);">
                                        <?php echo number_format($agent['submissions'] ?? 0); ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-weight:600;color:var(--secondary);">
                                        <?php echo number_format($agent['checkins'] ?? 0); ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-weight:600;">
                                        <?php if (($agent['incidents'] ?? 0) > 0): ?>
                                            <span style="color:var(--danger);"><?php echo number_format($agent['incidents'] ?? 0); ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-size:0.7rem;color:var(--gray-500);">
                                        <?php if ($agent['last_login_at']): ?>
                                            <?php echo date('M j, Y', strtotime($agent['last_login_at'])); ?>
                                            <div style="font-size:0.6rem;color:var(--gray-400);">
                                                <?php echo date('g:i A', strtotime($agent['last_login_at'])); ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
                                            <a href="coordinator-view.php?id=<?php echo $agent['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:6px;background:var(--primary);color:white;text-decoration:none;font-size:0.65rem;" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="coordinator-edit.php?id=<?php echo $agent['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:6px;background:var(--gray-200);color:var(--gray-700);text-decoration:none;font-size:0.65rem;" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="coordinator-suspend.php?id=<?php echo $agent['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:6px;background:#FEE2E2;color:#991B1B;text-decoration:none;font-size:0.65rem;" title="Suspend" onclick="return confirm('Suspend this agent?')">
                                                <i class="fas fa-user-slash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="padding:40px;text-align:center;color:var(--gray-500);">
                    <i class="fas fa-user-tie" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                    <p>No PU agents found</p>
                    <a href="coordinators-create.php?<?php echo $pu_id > 0 ? 'pu=' . $pu_id : ($ward_id > 0 ? 'ward=' . $ward_id : ''); ?>&level=pu_agent" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-block;margin-top:12px;">
                        <i class="fas fa-user-plus"></i> Add First Agent
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
.btn-sm:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.btn-secondary:hover { background: var(--gray-200); transform: translateY(-1px); }
.btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3); }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
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