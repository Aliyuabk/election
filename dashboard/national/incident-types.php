<?php
// ============================================================
// NATIONAL COORDINATOR - INCIDENT TYPES MANAGEMENT
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

$db = getDB();

// ============================================================
// INCIDENT TYPES DEFINITION
// ============================================================
$incident_types = [
    'violence' => [
        'label' => 'Violence',
        'icon' => 'fa-fist-raised',
        'color' => '#EF4444',
        'description' => 'Physical violence, assault, or fighting'
    ],
    'intimidation' => [
        'label' => 'Intimidation',
        'icon' => 'fa-skull',
        'color' => '#F59E0B',
        'description' => 'Threats, coercion, or intimidation tactics'
    ],
    'ballot_stuffing' => [
        'label' => 'Ballot Stuffing',
        'icon' => 'fa-boxes',
        'color' => '#DC2626',
        'description' => 'Illegal insertion of fraudulent ballots'
    ],
    'vote_buying' => [
        'label' => 'Vote Buying',
        'icon' => 'fa-money-bill-wave',
        'color' => '#D97706',
        'description' => 'Offering money or gifts in exchange for votes'
    ],
    'voter_suppression' => [
        'label' => 'Voter Suppression',
        'icon' => 'fa-user-slash',
        'color' => '#6B7280',
        'description' => 'Obstruction or discouragement of voters'
    ],
    'material_shortage' => [
        'label' => 'Material Shortage',
        'icon' => 'fa-box-open',
        'color' => '#3B82F6',
        'description' => 'Lack of election materials or supplies'
    ],
    'delay' => [
        'label' => 'Delay',
        'icon' => 'fa-clock',
        'color' => '#F59E0B',
        'description' => 'Late arrival or delays in election process'
    ],
    'technical_issue' => [
        'label' => 'Technical Issue',
        'icon' => 'fa-microchip',
        'color' => '#8B5CF6',
        'description' => 'Equipment or system malfunctions'
    ],
    'other' => [
        'label' => 'Other',
        'icon' => 'fa-ellipsis-h',
        'color' => '#6B7280',
        'description' => 'Other types of incidents'
    ],
    'panic_button' => [
        'label' => 'Panic Button',
        'icon' => 'fa-bell',
        'color' => '#EF4444',
        'description' => 'Emergency panic alert'
    ]
];

// ============================================================
// FETCH INCIDENT STATISTICS BY TYPE
// ============================================================
$type_stats = [];
$total_incidents = 0;

try {
    $stmt = $db->prepare("
        SELECT 
            incident_type,
            COUNT(*) as count,
            SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_count,
            SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high_count,
            SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium_count,
            SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low_count,
            SUM(CASE WHEN status = 'reported' THEN 1 ELSE 0 END) as open_count,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count
        FROM incidents
        WHERE tenant_id = ?
        GROUP BY incident_type
        ORDER BY count DESC
    ");
    $stmt->execute([$tenant_id]);
    $type_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total incidents
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM incidents WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $total_incidents = $stmt->fetchColumn() ?? 0;
    
} catch (Exception $e) {
    error_log("Incident Types Error: " . $e->getMessage());
}

// ============================================================
// FETCH RECENT INCIDENTS BY TYPE
// ============================================================
$recent_incidents = [];
try {
    $stmt = $db->prepare("
        SELECT 
            i.*,
            u.full_name as reporter_name,
            s.name as state_name,
            l.name as lga_name
        FROM incidents i
        LEFT JOIN users u ON i.reporter_id = u.id
        LEFT JOIN states s ON i.state_id = s.id
        LEFT JOIN lgas l ON i.lga_id = l.id
        WHERE i.tenant_id = ?
        ORDER BY i.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$tenant_id]);
    $recent_incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_incidents = [];
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Incident Types';
$page_subtitle = 'Incident classification and statistics';
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
                <a href="incidents.php" style="text-decoration:none;color:var(--gray-500);">Incidents</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Incident Types</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-tags" style="color:var(--primary);"></i>
                        Incident Types
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-chart-pie"></i> 
                        <?php echo number_format($total_incidents); ?> total incidents across <?php echo count(array_filter($type_stats, function($stat) { return $stat['count'] > 0; })); ?> types
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="incident-create.php" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-plus"></i> Report Incident
                    </a>
                    <a href="incidents.php" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($total_incidents); ?></div>
                <div class="stat-label">Total Incidents</div>
                <div class="stat-change">All types</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-number">
                    <?php 
                    $open_count = 0;
                    foreach ($type_stats as $stat) {
                        $open_count += $stat['open_count'] ?? 0;
                    }
                    echo number_format($open_count);
                    ?>
                </div>
                <div class="stat-label">Open Incidents</div>
                <div class="stat-change down"><i class="fas fa-hourglass-half"></i> Active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number">
                    <?php 
                    $resolved_count = 0;
                    foreach ($type_stats as $stat) {
                        $resolved_count += $stat['resolved_count'] ?? 0;
                    }
                    echo number_format($resolved_count);
                    ?>
                </div>
                <div class="stat-label">Resolved</div>
                <div class="stat-change up"><i class="fas fa-check"></i> Completed</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-tags"></i></div>
                <div class="stat-number"><?php echo count($incident_types); ?></div>
                <div class="stat-label">Total Types</div>
                <div class="stat-change">Classifications</div>
            </div>
        </div>

        <!-- Incident Types Grid -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-bottom:20px;">
            <?php foreach ($incident_types as $key => $type): 
                $stat = null;
                foreach ($type_stats as $s) {
                    if ($s['incident_type'] == $key) {
                        $stat = $s;
                        break;
                    }
                }
                $count = $stat['count'] ?? 0;
                $critical = $stat['critical_count'] ?? 0;
                $open = $stat['open_count'] ?? 0;
                $resolved = $stat['resolved_count'] ?? 0;
            ?>
                <div style="background:white;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);transition:var(--transition);hover:transform:translateY(-4px);hover:box-shadow:var(--shadow-hover);">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                        <div style="width:44px;height:44px;border-radius:50%;background:<?php echo $type['color']; ?>20;display:flex;align-items:center;justify-content:center;color:<?php echo $type['color']; ?>;font-size:1.1rem;">
                            <i class="fas <?php echo $type['icon']; ?>"></i>
                        </div>
                        <div style="flex:1;">
                            <div style="font-weight:600;font-size:0.95rem;color:var(--gray-800);">
                                <?php echo $type['label']; ?>
                                <?php if ($count > 0): ?>
                                    <span style="font-size:0.7rem;font-weight:400;color:var(--gray-400);">(<?php echo number_format($count); ?>)</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:0.7rem;color:var(--gray-400);"><?php echo $type['description']; ?></div>
                        </div>
                        <?php if ($critical > 0): ?>
                            <span style="padding:2px 10px;border-radius:12px;font-size:0.6rem;font-weight:600;background:#FEE2E2;color:#991B1B;">
                                <?php echo $critical; ?> critical
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($count > 0): ?>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;padding-top:12px;border-top:1px solid var(--gray-100);">
                            <div style="text-align:center;">
                                <div style="font-weight:600;font-size:1.1rem;color:var(--gray-700);"><?php echo number_format($count); ?></div>
                                <div style="font-size:0.6rem;color:var(--gray-400);">Total</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-weight:600;font-size:1.1rem;color:var(--warning);"><?php echo number_format($open); ?></div>
                                <div style="font-size:0.6rem;color:var(--gray-400);">Open</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-weight:600;font-size:1.1rem;color:var(--secondary);"><?php echo number_format($resolved); ?></div>
                                <div style="font-size:0.6rem;color:var(--gray-400);">Resolved</div>
                            </div>
                        </div>
                        
                        <!-- Progress Bar -->
                        <div style="margin-top:8px;">
                            <div style="width:100%;height:4px;background:var(--gray-100);border-radius:4px;overflow:hidden;">
                                <div style="width:<?php echo $count > 0 ? round(($resolved / $count) * 100) : 0; ?>%;height:100%;background:<?php echo $type['color']; ?>;border-radius:4px;"></div>
                            </div>
                            <div style="font-size:0.55rem;color:var(--gray-400);margin-top:2px;text-align:right;">
                                <?php echo $count > 0 ? round(($resolved / $count) * 100) : 0; ?>% resolved
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="padding:8px 0;text-align:center;color:var(--gray-400);font-size:0.75rem;">
                            <i class="fas fa-check-circle" style="color:#10B981;"></i> No incidents of this type
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Recent Incidents by Type -->
        <?php if (count($recent_incidents) > 0): ?>
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);">
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0;">
                    <i class="fas fa-clock" style="color:var(--primary);margin-right:6px;"></i>
                    Recent Incidents
                </h4>
                <a href="incidents.php" style="font-size:0.7rem;color:var(--primary);text-decoration:none;">View All →</a>
            </div>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                    <thead style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
                        <tr>
                            <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--gray-600);">Type</th>
                            <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--gray-600);">Title</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Location</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Severity</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Status</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Reported</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_incidents as $incident): 
                            $type_info = $incident_types[$incident['incident_type']] ?? null;
                            $type_color = $type_info['color'] ?? '#6B7280';
                            $type_icon = $type_info['icon'] ?? 'fa-circle';
                            $type_label = $type_info['label'] ?? ucfirst($incident['incident_type']);
                            
                            $severity_colors = [
                                'critical' => '#EF4444',
                                'high' => '#F59E0B',
                                'medium' => '#3B82F6',
                                'low' => '#10B981'
                            ];
                            $status_colors = [
                                'reported' => '#EF4444',
                                'acknowledged' => '#F59E0B',
                                'investigating' => '#3B82F6',
                                'resolved' => '#10B981',
                                'escalated' => '#8B5CF6',
                                'false_alarm' => '#6B7280'
                            ];
                            $severity_color = $severity_colors[$incident['severity']] ?? '#6B7280';
                            $status_color = $status_colors[$incident['status']] ?? '#6B7280';
                            
                            $location = '';
                            if ($incident['lga_name']) {
                                $location = $incident['lga_name'];
                            } elseif ($incident['state_name']) {
                                $location = $incident['state_name'];
                            } else {
                                $location = 'Unknown';
                            }
                        ?>
                            <tr style="border-bottom:1px solid var(--gray-100);transition:var(--transition);hover:background:var(--gray-50);">
                                <td style="padding:10px 14px;">
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <i class="fas <?php echo $type_icon; ?>" style="color:<?php echo $type_color; ?>;"></i>
                                        <span style="font-size:0.75rem;font-weight:500;"><?php echo $type_label; ?></span>
                                    </div>
                                </td>
                                <td style="padding:10px 14px;">
                                    <div style="font-weight:500;font-size:0.85rem;"><?php echo htmlspecialchars($incident['title']); ?></div>
                                    <div style="font-size:0.65rem;color:var(--gray-400);">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($incident['reporter_name'] ?? 'Unknown'); ?>
                                    </div>
                                </td>
                                <td style="padding:10px 14px;text-align:center;font-size:0.75rem;">
                                    <?php echo htmlspecialchars($location); ?>
                                </td>
                                <td style="padding:10px 14px;text-align:center;">
                                    <span style="display:inline-block;padding:2px 10px;border-radius:10px;font-size:0.65rem;font-weight:600;background:<?php echo $severity_color; ?>20;color:<?php echo $severity_color; ?>;">
                                        <?php echo ucfirst($incident['severity']); ?>
                                    </span>
                                </td>
                                <td style="padding:10px 14px;text-align:center;">
                                    <span style="display:inline-block;padding:2px 10px;border-radius:10px;font-size:0.65rem;font-weight:600;background:<?php echo $status_color; ?>20;color:<?php echo $status_color; ?>;">
                                        <?php echo ucfirst($incident['status']); ?>
                                    </span>
                                </td>
                                <td style="padding:10px 14px;text-align:center;font-size:0.7rem;color:var(--gray-500);">
                                    <?php echo date('M j, Y', strtotime($incident['created_at'])); ?>
                                    <div style="font-size:0.6rem;color:var(--gray-400);">
                                        <?php echo date('g:i A', strtotime($incident['created_at'])); ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<style>
.stat-icon.red { background: #FEF2F2; color: #EF4444; }
.stat-icon.yellow { background: #FFFBEB; color: #F59E0B; }
.stat-icon.green { background: #ECFDF5; color: #10B981; }
.stat-icon.purple { background: #F5F3FF; color: #8B5CF6; }

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-secondary:hover {
    background: var(--gray-200);
    transform: translateY(-1px);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    div[style*="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))"] {
        grid-template-columns: 1fr !important;
    }
    table {
        font-size: 0.7rem;
    }
    th, td {
        padding: 6px 8px !important;
    }
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