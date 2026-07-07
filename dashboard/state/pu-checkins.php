<?php
// ============================================================
// STATE COORDINATOR - POLLING UNIT CHECK-INS
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

// Get PU ID from URL
$pu_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$agent_id = isset($_GET['agent']) ? intval($_GET['agent']) : 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

if ($pu_id <= 0) {
    header('Location: monitor-lgas.php?error=invalid_pu');
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

try {
    $stmt = $db->prepare("
        SELECT 
            pu.*,
            w.name as ward_name,
            l.name as lga_name,
            s.name as state_name
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        JOIN states s ON l.state_id = s.id
        WHERE pu.id = ? AND l.state_id = ?
    ");
    $stmt->execute([$pu_id, $state_id]);
    $pu_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pu_data) {
        header('Location: monitor-lgas.php?error=pu_not_found');
        exit();
    }
    
    $ward_name = $pu_data['ward_name'];
    $lga_name = $pu_data['lga_name'];
    $state_name = $pu_data['state_name'];
    
} catch (Exception $e) {
    error_log("PU Check-ins Error: " . $e->getMessage());
    header('Location: monitor-lgas.php?error=database_error');
    exit();
}

// ============================================================
// FETCH CHECK-INS
// ============================================================
$checkins = [];
$total_checkins = 0;

try {
    $where_clauses = ['ac.tenant_id = ?', 'ac.pu_id = ?'];
    $params = [$tenant_id, $pu_id];
    
    if ($agent_id > 0) {
        $where_clauses[] = 'ac.agent_id = ?';
        $params[] = $agent_id;
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    
    // Count total
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM agent_checkins ac WHERE $where_sql");
    $count_stmt->execute($params);
    $total_checkins = $count_stmt->fetchColumn();

    // Fetch check-ins
    $stmt = $db->prepare("
        SELECT 
            ac.*,
            u.full_name as agent_name,
            u.email as agent_email,
            u.phone as agent_phone
        FROM agent_checkins ac
        LEFT JOIN users u ON ac.agent_id = u.id
        WHERE $where_sql
        ORDER BY ac.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $query_params = array_merge($params, [$limit, $offset]);
    $stmt->execute($query_params);
    $checkins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Check-ins Error: " . $e->getMessage());
}

// ============================================================
// FETCH AGENTS FOR FILTER
// ============================================================
$agents = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT u.id, u.full_name
        FROM users u
        JOIN agent_checkins ac ON ac.agent_id = u.id
        WHERE ac.tenant_id = ? AND ac.pu_id = ?
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $pu_id]);
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $agents = [];
}

// ============================================================
// CHECK-IN TYPE LABELS AND COLORS
// ============================================================
$checkin_types = [
    'arrival' => ['label' => 'Arrival', 'color' => '#10B981', 'icon' => 'fa-sign-in-alt'],
    'departure' => ['label' => 'Departure', 'color' => '#EF4444', 'icon' => 'fa-sign-out-alt'],
    'material_received' => ['label' => 'Materials Received', 'color' => '#3B82F6', 'icon' => 'fa-box'],
    'accreditation_started' => ['label' => 'Accreditation Started', 'color' => '#F59E0B', 'icon' => 'fa-clipboard-check'],
    'voting_started' => ['label' => 'Voting Started', 'color' => '#8B5CF6', 'icon' => 'fa-vote-yea'],
    'voting_ended' => ['label' => 'Voting Ended', 'color' => '#6B7280', 'icon' => 'fa-stop'],
    'counting_started' => ['label' => 'Counting Started', 'color' => '#EC4899', 'icon' => 'fa-calculator'],
    'counting_ended' => ['label' => 'Counting Ended', 'color' => '#14B8A6', 'icon' => 'fa-check-double']
];

$total_pages = ceil($total_checkins / $limit);

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'PU Check-ins';
$page_subtitle = $pu_data['name'] ?? 'Polling Unit';
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
                <a href="monitor-lgas.php" style="text-decoration:none;color:var(--gray-500);">Monitor LGAs</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="lga-dashboard.php?id=<?php echo $pu_data['lga_id']; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($lga_name); ?></a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="ward-dashboard.php?id=<?php echo $pu_data['ward_id']; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($ward_name); ?></a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="pu-dashboard.php?id=<?php echo $pu_id; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($pu_data['name']); ?></a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Check-ins</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-sign-in-alt" style="color:var(--primary);"></i>
                        PU Check-ins
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-flag-checkered"></i> 
                        <?php echo htmlspecialchars($pu_data['name']); ?> • 
                        <?php echo number_format($total_checkins); ?> check-ins
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
                <div class="stat-icon blue"><i class="fas fa-sign-in-alt"></i></div>
                <div class="stat-number"><?php echo number_format($total_checkins); ?></div>
                <div class="stat-label">Total Check-ins</div>
                <div class="stat-change">All records</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format(count($agents)); ?></div>
                <div class="stat-label">Active Agents</div>
                <div class="stat-change"><i class="fas fa-user-check"></i> Unique</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-number">
                    <?php 
                    $recent_checkins = 0;
                    foreach ($checkins as $c) {
                        if (strtotime($c['created_at']) > strtotime('-24 hours')) {
                            $recent_checkins++;
                        }
                    }
                    echo number_format($recent_checkins);
                    ?>
                </div>
                <div class="stat-label">Last 24 Hours</div>
                <div class="stat-change"><i class="fas fa-clock"></i> Recent activity</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number">
                    <?php 
                    $arrivals = 0;
                    foreach ($checkins as $c) {
                        if ($c['checkin_type'] === 'arrival') {
                            $arrivals++;
                        }
                    }
                    echo number_format($arrivals);
                    ?>
                </div>
                <div class="stat-label">Arrivals</div>
                <div class="stat-change"><i class="fas fa-check"></i> Confirmed</div>
            </div>
        </div>

        <!-- Filters -->
        <div style="background:white;border-radius:var(--radius);padding:12px 16px;margin-bottom:16px;border:1px solid var(--gray-200);">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                <input type="hidden" name="id" value="<?php echo $pu_id; ?>">
                
                <div style="min-width:180px;">
                    <select name="agent" class="form-select" style="width:100%;padding:6px 10px;border:1px solid var(--gray-200);border-radius:8px;font-family:'Inter',sans-serif;font-size:0.7rem;background:white;">
                        <option value="">All Agents</option>
                        <?php foreach ($agents as $agent): ?>
                            <option value="<?php echo $agent['id']; ?>" <?php echo $agent_id == $agent['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($agent['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-primary" style="padding:6px 14px;background:var(--primary);color:white;border:none;border-radius:8px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.7rem;cursor:pointer;transition:var(--transition);">
                    <i class="fas fa-filter"></i> Filter
                </button>
                
                <?php if ($agent_id > 0): ?>
                    <a href="pu-checkins.php?id=<?php echo $pu_id; ?>" class="btn-reset" style="padding:6px 10px;background:var(--gray-100);color:var(--gray-600);border:none;border-radius:8px;font-family:'Inter',sans-serif;font-weight:500;font-size:0.7rem;cursor:pointer;text-decoration:none;transition:var(--transition);">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Check-ins Table -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);">
            <div style="padding:10px 16px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.85rem;font-weight:600;margin:0;">
                    <i class="fas fa-list" style="color:var(--primary);margin-right:6px;"></i>
                    Check-in Records
                    <span style="font-size:0.65rem;font-weight:400;color:var(--gray-400);margin-left:8px;">
                        (<?php echo number_format($total_checkins); ?>)
                    </span>
                </h4>
            </div>
            
            <?php if (count($checkins) > 0): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.7rem;">
                        <thead style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
                            <tr>
                                <th style="padding:6px 10px;text-align:left;font-weight:600;color:var(--gray-600);">Agent</th>
                                <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">Check-in Type</th>
                                <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">GPS Location</th>
                                <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">Distance</th>
                                <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">Device</th>
                                <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">Battery</th>
                                <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($checkins as $checkin): 
                                $type_info = $checkin_types[$checkin['checkin_type']] ?? ['label' => ucfirst($checkin['checkin_type']), 'color' => '#6B7280', 'icon' => 'fa-circle'];
                                $type_color = $type_info['color'];
                                $type_label = $type_info['label'];
                                $type_icon = $type_info['icon'];
                            ?>
                                <tr style="border-bottom:1px solid var(--gray-100);transition:var(--transition);hover:background:var(--gray-50);">
                                    <td style="padding:6px 10px;">
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <div style="width:28px;height:28px;border-radius:50%;background:var(--secondary);color:white;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:0.65rem;flex-shrink:0;">
                                                <?php echo strtoupper(substr($checkin['agent_name'] ?? 'U', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:500;font-size:0.75rem;"><?php echo htmlspecialchars($checkin['agent_name'] ?? 'Unknown'); ?></div>
                                                <div style="font-size:0.55rem;color:var(--gray-400);"><?php echo htmlspecialchars($checkin['agent_email'] ?? 'N/A'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding:6px 10px;text-align:center;">
                                        <span style="display:inline-block;padding:1px 8px;border-radius:8px;font-size:0.55rem;font-weight:600;background:<?php echo $type_color; ?>20;color:<?php echo $type_color; ?>;">
                                            <i class="fas <?php echo $type_icon; ?>"></i>
                                            <?php echo $type_label; ?>
                                        </span>
                                    </td>
                                    <td style="padding:6px 10px;text-align:center;font-size:0.65rem;">
                                        <?php if (!empty($checkin['gps_lat']) && !empty($checkin['gps_lng'])): ?>
                                            <div><?php echo number_format($checkin['gps_lat'], 6); ?></div>
                                            <div style="font-size:0.5rem;color:var(--gray-400);"><?php echo number_format($checkin['gps_lng'], 6); ?></div>
                                            <?php if (!empty($checkin['gps_accuracy'])): ?>
                                                <div style="font-size:0.5rem;color:var(--gray-400);">±<?php echo $checkin['gps_accuracy']; ?>m</div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:6px 10px;text-align:center;font-weight:500;">
                                        <?php if (!empty($checkin['gps_distance_from_pu'])): ?>
                                            <span style="color:<?php echo $checkin['gps_distance_from_pu'] < 50 ? '#10B981' : ($checkin['gps_distance_from_pu'] < 200 ? '#F59E0B' : '#EF4444'); ?>;">
                                                <?php echo number_format($checkin['gps_distance_from_pu'], 1); ?>m
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:6px 10px;text-align:center;font-size:0.65rem;color:var(--gray-500);">
                                        <?php if (!empty($checkin['device_id'])): ?>
                                            <div><?php echo htmlspecialchars(substr($checkin['device_id'], 0, 10)) . '...'; ?></div>
                                            <div style="font-size:0.5rem;color:var(--gray-400);">
                                                <?php echo $checkin['network_type'] ? strtoupper($checkin['network_type']) : 'N/A'; ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:6px 10px;text-align:center;">
                                        <?php if (!empty($checkin['device_battery'])): ?>
                                            <div style="display:flex;align-items:center;gap:3px;justify-content:center;">
                                                <div style="width:25px;height:10px;background:var(--gray-200);border-radius:3px;overflow:hidden;border:1px solid var(--gray-300);">
                                                    <div style="width:<?php echo $checkin['device_battery']; ?>%;height:100%;background:<?php echo $checkin['device_battery'] > 50 ? '#10B981' : ($checkin['device_battery'] > 20 ? '#F59E0B' : '#EF4444'); ?>;border-radius:3px;"></div>
                                                </div>
                                                <span style="font-size:0.6rem;font-weight:600;"><?php echo $checkin['device_battery']; ?>%</span>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:6px 10px;text-align:center;font-size:0.65rem;color:var(--gray-500);">
                                        <?php echo date('M j, Y', strtotime($checkin['created_at'])); ?>
                                        <div style="font-size:0.5rem;color:var(--gray-400);">
                                            <?php echo date('g:i A', strtotime($checkin['created_at'])); ?>
                                        </div>
                                        <?php if ($checkin['is_offline_sync']): ?>
                                            <span style="font-size:0.5rem;background:#FEF3C7;color:#92400E;padding:1px 4px;border-radius:6px;">
                                                <i class="fas fa-wifi"></i> Offline
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="padding:40px 20px;text-align:center;color:var(--gray-500);">
                    <i class="fas fa-sign-in-alt" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                    <p style="font-size:0.85rem;">No check-in records found for this polling unit.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;padding:10px 0;">
                <div style="font-size:0.65rem;color:var(--gray-500);">
                    Showing <?php echo (($page - 1) * $limit) + 1; ?> - <?php echo min($page * $limit, $total_checkins); ?> of <?php echo number_format($total_checkins); ?>
                </div>
                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?id=<?php echo $pu_id; ?>&page=<?php echo $page - 1; ?>&agent=<?php echo $agent_id; ?>" 
                           class="btn-page" style="padding:4px 10px;border:1px solid var(--gray-200);border-radius:6px;text-decoration:none;color:var(--gray-600);font-size:0.65rem;transition:var(--transition);">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    if ($start_page > 1) {
                        echo '<a href="?id=' . $pu_id . '&page=1&agent=' . $agent_id . '" class="btn-page" style="padding:4px 10px;border:1px solid var(--gray-200);border-radius:6px;text-decoration:none;color:var(--gray-600);font-size:0.65rem;transition:var(--transition);">1</a>';
                        if ($start_page > 2) echo '<span style="padding:4px 6px;color:var(--gray-400);font-size:0.65rem;">...</span>';
                    }
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?id=<?php echo $pu_id; ?>&page=<?php echo $i; ?>&agent=<?php echo $agent_id; ?>" 
                           class="btn-page <?php echo $i == $page ? 'active' : ''; ?>" 
                           style="padding:4px 10px;border:1px solid <?php echo $i == $page ? 'var(--primary)' : 'var(--gray-200)'; ?>;border-radius:6px;text-decoration:none;color:<?php echo $i == $page ? 'white' : 'var(--gray-600)'; ?>;font-size:0.65rem;transition:var(--transition);background:<?php echo $i == $page ? 'var(--primary)' : 'transparent'; ?>;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; 
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span style="padding:4px 6px;color:var(--gray-400);font-size:0.65rem;">...</span>';
                        echo '<a href="?id=' . $pu_id . '&page=' . $total_pages . '&agent=' . $agent_id . '" class="btn-page" style="padding:4px 10px;border:1px solid var(--gray-200);border-radius:6px;text-decoration:none;color:var(--gray-600);font-size:0.65rem;transition:var(--transition);">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?id=<?php echo $pu_id; ?>&page=<?php echo $page + 1; ?>&agent=<?php echo $agent_id; ?>" 
                           class="btn-page" style="padding:4px 10px;border:1px solid var(--gray-200);border-radius:6px;text-decoration:none;color:var(--gray-600);font-size:0.65rem;transition:var(--transition);">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
.btn-sm:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.btn-page:hover { background: var(--gray-50); border-color: var(--gray-300); }
.btn-page.active { background: var(--primary); color: white; border-color: var(--primary); }
.btn-page.active:hover { background: var(--primary-dark); }
.btn-secondary:hover { background: var(--gray-200); transform: translateY(-1px); }
.btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3); }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    table { font-size: 0.6rem; }
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