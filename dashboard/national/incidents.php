<?php
// ============================================================
// NATIONAL COORDINATOR - INCIDENT MANAGEMENT
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
$lga_id = isset($_GET['lga']) ? intval($_GET['lga']) : 0;
$state_id = isset($_GET['state']) ? intval($_GET['state']) : 0;
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$severity_filter = isset($_GET['severity']) ? trim($_GET['severity']) : '';
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$db = getDB();

// ============================================================
// FETCH LOCATION DATA
// ============================================================
$location_name = '';
$back_url = 'monitor-states.php';

if ($lga_id > 0) {
    try {
        $stmt = $db->prepare("SELECT name FROM lgas WHERE id = ?");
        $stmt->execute([$lga_id]);
        $location_name = $stmt->fetchColumn() ?: 'LGA';
        $back_url = "lga-dashboard.php?id=$lga_id";
    } catch (Exception $e) {
        $location_name = 'LGA';
    }
} elseif ($state_id > 0) {
    try {
        $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
        $stmt->execute([$state_id]);
        $location_name = $stmt->fetchColumn() ?: 'State';
        $back_url = "view-state.php?id=$state_id";
    } catch (Exception $e) {
        $location_name = 'State';
    }
}

// ============================================================
// BUILD QUERY
// ============================================================
$where_clauses = ['tenant_id = ?'];
$params = [$tenant_id];

if ($lga_id > 0) {
    $where_clauses[] = 'lga_id = ?';
    $params[] = $lga_id;
} elseif ($state_id > 0) {
    $where_clauses[] = 'state_id = ?';
    $params[] = $state_id;
}

if (!empty($status_filter)) {
    $where_clauses[] = 'status = ?';
    $params[] = $status_filter;
}

if (!empty($severity_filter)) {
    $where_clauses[] = 'severity = ?';
    $params[] = $severity_filter;
}

if (!empty($type_filter)) {
    $where_clauses[] = 'incident_type = ?';
    $params[] = $type_filter;
}

if (!empty($search)) {
    $where_clauses[] = '(title LIKE ? OR description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = implode(' AND ', $where_clauses);

// ============================================================
// FETCH INCIDENTS
// ============================================================
$incidents = [];
$total_incidents = 0;

try {
    // Count total
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM incidents WHERE $where_sql");
    $count_stmt->execute($params);
    $total_incidents = $count_stmt->fetchColumn();

    // Fetch incidents
    $stmt = $db->prepare("
        SELECT 
            i.*,
            u.full_name as reporter_name,
            u2.full_name as assigned_to_name,
            u3.full_name as resolved_by_name,
            s.name as state_name,
            l.name as lga_name,
            w.name as ward_name,
            pu.name as pu_name
        FROM incidents i
        LEFT JOIN users u ON i.reporter_id = u.id
        LEFT JOIN users u2 ON i.assigned_to = u2.id
        LEFT JOIN users u3 ON i.resolved_by = u3.id
        LEFT JOIN states s ON i.state_id = s.id
        LEFT JOIN lgas l ON i.lga_id = l.id
        LEFT JOIN wards w ON i.ward_id = w.id
        LEFT JOIN polling_units pu ON i.pu_id = pu.id
        WHERE $where_sql
        ORDER BY 
            CASE WHEN i.severity = 'critical' THEN 1
                 WHEN i.severity = 'high' THEN 2
                 WHEN i.severity = 'medium' THEN 3
                 ELSE 4 END,
            i.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $query_params = array_merge($params, [$limit, $offset]);
    $stmt->execute($query_params);
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Incidents Error: " . $e->getMessage());
    $incidents = [];
}
// ============================================================
// GET STATISTICS
// ============================================================
$stats = [
    'total' => 0,
    'critical' => 0,
    'high' => 0,
    'medium' => 0,
    'low' => 0,
    'reported' => 0,
    'investigating' => 0,
    'resolved' => 0,
    'panic' => 0
];

try {
    $stats_stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical,
            SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high,
            SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium,
            SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low,
            SUM(CASE WHEN status = 'reported' THEN 1 ELSE 0 END) as reported,
            SUM(CASE WHEN status = 'investigating' THEN 1 ELSE 0 END) as investigating,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN is_panic = 1 THEN 1 ELSE 0 END) as panic
        FROM incidents
        WHERE $where_sql
    ");
    $stats_stmt->execute($params);
    $result = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ensure all values are set with defaults
    $stats['total'] = $result['total'] ?? 0;
    $stats['critical'] = $result['critical'] ?? 0;
    $stats['high'] = $result['high'] ?? 0;
    $stats['medium'] = $result['medium'] ?? 0;
    $stats['low'] = $result['low'] ?? 0;
    $stats['reported'] = $result['reported'] ?? 0;
    $stats['investigating'] = $result['investigating'] ?? 0;
    $stats['resolved'] = $result['resolved'] ?? 0;
    $stats['panic'] = $result['panic'] ?? 0;
    
} catch (Exception $e) {
    error_log("Stats error: " . $e->getMessage());
}

$total_pages = ceil($total_incidents / $limit);

// Incident type labels
$incident_types = [
    'violence' => 'Violence',
    'intimidation' => 'Intimidation',
    'ballot_stuffing' => 'Ballot Stuffing',
    'vote_buying' => 'Vote Buying',
    'voter_suppression' => 'Voter Suppression',
    'material_shortage' => 'Material Shortage',
    'delay' => 'Delay',
    'technical_issue' => 'Technical Issue',
    'other' => 'Other',
    'panic_button' => 'Panic Button'
];

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

$status_labels = [
    'reported' => 'Reported',
    'acknowledged' => 'Acknowledged',
    'investigating' => 'Investigating',
    'resolved' => 'Resolved',
    'escalated' => 'Escalated',
    'false_alarm' => 'False Alarm'
];

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Incident Management';
$page_subtitle = $location_name ? "Location: $location_name" : 'All Incidents';
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
                <?php if ($lga_id > 0): ?>
                    <a href="<?php echo $back_url; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($location_name); ?></a>
                    <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <?php elseif ($state_id > 0): ?>
                    <a href="<?php echo $back_url; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($location_name); ?></a>
                    <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <?php endif; ?>
                <span style="font-weight:600;color:var(--gray-800);">Incidents</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        Incident Management
                        <?php if ($stats['panic'] > 0): ?>
                            <span style="font-size:0.7rem;background:#EF4444;color:white;padding:2px 12px;border-radius:20px;font-weight:500;margin-left:8px;animation:pulse 1.5s ease-in-out infinite;">
                                🔴 <?php echo $stats['panic']; ?> Panic Alerts
                            </span>
                        <?php endif; ?>
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <?php echo number_format($stats['total']); ?> total incidents
                        <?php if ($stats['critical'] > 0): ?>
                            • <span style="color:#EF4444;font-weight:600;"><?php echo $stats['critical']; ?> critical</span>
                        <?php endif; ?>
                        <?php if ($stats['reported'] > 0): ?>
                            • <span style="color:#F59E0B;font-weight:600;"><?php echo $stats['reported']; ?> open</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="incident-create.php" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-plus"></i> Report Incident
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
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
                <div class="stat-label">Total Incidents</div>
                <div class="stat-change">All recorded</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-skull-crossbones"></i></div>
                <div class="stat-number"><?php echo number_format($stats['critical'] ?? 0); ?></div>
                <div class="stat-label">Critical</div>
                <div class="stat-change down"><i class="fas fa-exclamation-circle"></i> Immediate attention</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format(($stats['reported'] ?? 0) + ($stats['investigating'] ?? 0)); ?></div>
                <div class="stat-label">Open Incidents</div>
                <div class="stat-change down"><i class="fas fa-hourglass-half"></i> In progress</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['resolved'] ?? 0); ?></div>
                <div class="stat-label">Resolved</div>
                <div class="stat-change up"><i class="fas fa-check"></i> Completed</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-chart-bar"></i></div>
                <div class="stat-number"><?php echo number_format($stats['high'] ?? 0); ?></div>
                <div class="stat-label">High Priority</div>
                <div class="stat-change"><i class="fas fa-arrow-up"></i> Needs review</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-flag"></i></div>
                <div class="stat-number"><?php echo number_format($stats['panic'] ?? 0); ?></div>
                <div class="stat-label">Panic Alerts</div>
                <div class="stat-change"><i class="fas fa-bell"></i> Emergency</div>
            </div>
        </div>

        <!-- Filters -->
        <div style="background:white;border-radius:var(--radius);padding:16px 20px;margin-bottom:20px;border:1px solid var(--gray-200);">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
                <?php if ($lga_id > 0): ?>
                    <input type="hidden" name="lga" value="<?php echo $lga_id; ?>">
                <?php endif; ?>
                <?php if ($state_id > 0): ?>
                    <input type="hidden" name="state" value="<?php echo $state_id; ?>">
                <?php endif; ?>
                
                <div style="flex:1;min-width:150px;">
                    <div class="search-box" style="width:100%;">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search incidents..." value="<?php echo htmlspecialchars($search); ?>" />
                    </div>
                </div>
                
                <div style="min-width:130px;">
                    <select name="status" class="form-select" style="width:100%;padding:8px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;">
                        <option value="">All Status</option>
                        <?php foreach ($status_labels as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $status_filter == $key ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="min-width:130px;">
                    <select name="severity" class="form-select" style="width:100%;padding:8px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;">
                        <option value="">All Severity</option>
                        <option value="critical" <?php echo $severity_filter == 'critical' ? 'selected' : ''; ?>>Critical</option>
                        <option value="high" <?php echo $severity_filter == 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="medium" <?php echo $severity_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="low" <?php echo $severity_filter == 'low' ? 'selected' : ''; ?>>Low</option>
                    </select>
                </div>
                
                <div style="min-width:140px;">
                    <select name="type" class="form-select" style="width:100%;padding:8px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;">
                        <option value="">All Types</option>
                        <?php foreach ($incident_types as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $type_filter == $key ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-primary" style="padding:8px 24px;background:var(--primary);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.8rem;cursor:pointer;transition:var(--transition);">
                    <i class="fas fa-filter"></i> Filter
                </button>
                
                <?php if (!empty($search) || !empty($status_filter) || !empty($severity_filter) || !empty($type_filter)): ?>
                    <a href="?<?php echo $lga_id > 0 ? 'lga=' . $lga_id : ($state_id > 0 ? 'state=' . $state_id : ''); ?>" class="btn-reset" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-600);border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:500;font-size:0.8rem;cursor:pointer;text-decoration:none;transition:var(--transition);">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Incidents List -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0;">
                    <i class="fas fa-list" style="color:var(--danger);margin-right:6px;"></i>
                    Incident Reports
                </h4>
                <span style="font-size:0.75rem;color:var(--gray-500);"><?php echo count($incidents); ?> of <?php echo number_format($total_incidents); ?></span>
            </div>
            
            <?php if (count($incidents) > 0): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                        <thead style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
                            <tr>
                                <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--gray-600);">Incident</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Severity</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Status</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Location</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Reported</th>
                                <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incidents as $incident): 
                                $severity_color = $severity_colors[$incident['severity']] ?? '#6B7280';
                                $status_color = $status_colors[$incident['status']] ?? '#6B7280';
                                $status_label = $status_labels[$incident['status']] ?? ucfirst($incident['status']);
                                $type_label = $incident_types[$incident['incident_type']] ?? ucfirst($incident['incident_type']);
                                
                                $location = '';
                                if ($incident['pu_name']) {
                                    $location = $incident['pu_name'];
                                } elseif ($incident['ward_name']) {
                                    $location = $incident['ward_name'];
                                } elseif ($incident['lga_name']) {
                                    $location = $incident['lga_name'];
                                } elseif ($incident['state_name']) {
                                    $location = $incident['state_name'];
                                } else {
                                    $location = 'Unknown';
                                }
                            ?>
                                <tr style="border-bottom:1px solid var(--gray-100);transition:var(--transition);hover:background:var(--gray-50);">
                                    <td style="padding:10px 14px;">
                                        <div style="font-weight:500;color:var(--gray-800);">
                                            <?php if ($incident['is_panic']): ?>
                                                <span style="color:#EF4444;font-size:0.7rem;margin-right:4px;">🔴</span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($incident['title']); ?>
                                        </div>
                                        <div style="font-size:0.65rem;color:var(--gray-400);">
                                            <span class="badge" style="background:var(--gray-100);color:var(--gray-600);padding:1px 8px;border-radius:10px;">
                                                <?php echo $type_label; ?>
                                            </span>
                                            <span style="margin:0 4px;">•</span>
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($incident['reporter_name'] ?? 'Unknown'); ?>
                                        </div>
                                        <div style="font-size:0.65rem;color:var(--gray-400);margin-top:2px;max-width:250px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                            <?php echo htmlspecialchars(substr($incident['description'], 0, 60)) . (strlen($incident['description']) > 60 ? '...' : ''); ?>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <span style="display:inline-block;padding:2px 12px;border-radius:12px;font-size:0.65rem;font-weight:600;background:<?php echo $severity_color; ?>20;color:<?php echo $severity_color; ?>;">
                                            <?php echo ucfirst($incident['severity']); ?>
                                        </span>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <span style="display:inline-block;padding:2px 12px;border-radius:12px;font-size:0.65rem;font-weight:600;background:<?php echo $status_color; ?>20;color:<?php echo $status_color; ?>;">
                                            <?php echo $status_label; ?>
                                        </span>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-size:0.75rem;">
                                        <?php echo htmlspecialchars($location); ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-size:0.7rem;color:var(--gray-500);">
                                        <?php echo date('M j, Y', strtotime($incident['created_at'])); ?>
                                        <div style="font-size:0.6rem;color:var(--gray-400);">
                                            <?php echo date('g:i A', strtotime($incident['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
                                            <a href="incident-view.php?id=<?php echo $incident['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:6px;background:var(--primary);color:white;text-decoration:none;font-size:0.65rem;">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="incident-update.php?id=<?php echo $incident['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:6px;background:var(--gray-200);color:var(--gray-700);text-decoration:none;font-size:0.65rem;">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($incident['status'] !== 'resolved'): ?>
                                                <a href="incident-resolve.php?id=<?php echo $incident['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:6px;background:#10B981;color:white;text-decoration:none;font-size:0.65rem;">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($incident['status'] === 'reported'): ?>
                                                <a href="incident-escalate.php?id=<?php echo $incident['id']; ?>" class="btn-sm" style="padding:3px 10px;border-radius:6px;background:#8B5CF6;color:white;text-decoration:none;font-size:0.65rem;">
                                                    <i class="fas fa-arrow-up"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($incident['is_panic']): ?>
                                                <span style="padding:3px 8px;border-radius:6px;background:#FEE2E2;color:#991B1B;font-size:0.6rem;font-weight:600;">
                                                    <i class="fas fa-bell"></i> Panic
                                                </span>
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
                    <i class="fas fa-check-circle" style="font-size:3rem;display:block;margin-bottom:12px;color:#10B981;"></i>
                    <h3 style="font-size:1.1rem;font-weight:600;color:var(--gray-600);margin:0 0 8px;">No Incidents Found</h3>
                    <p style="font-size:0.85rem;color:var(--gray-400);margin:0;">All clear! No incidents match your current filters.</p>
                    <a href="incident-create.php" class="btn-primary" style="padding:10px 28px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.85rem;display:inline-flex;align-items:center;gap:8px;margin-top:16px;">
                        <i class="fas fa-plus"></i> Report Incident
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;padding:12px 0;">
                <div style="font-size:0.8rem;color:var(--gray-500);">
                    Showing <?php echo (($page - 1) * $limit) + 1; ?> - <?php echo min($page * $limit, $total_incidents); ?> of <?php echo number_format($total_incidents); ?> incidents
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&lga=<?php echo $lga_id; ?>&state=<?php echo $state_id; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&severity=<?php echo urlencode($severity_filter); ?>&type=<?php echo urlencode($type_filter); ?>" 
                           class="btn-page" style="padding:6px 14px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    if ($start_page > 1) {
                        echo '<a href="?page=1&lga=' . $lga_id . '&state=' . $state_id . '&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '&severity=' . urlencode($severity_filter) . '&type=' . urlencode($type_filter) . '" class="btn-page" style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">1</a>';
                        if ($start_page > 2) echo '<span style="padding:6px 8px;color:var(--gray-400);">...</span>';
                    }
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?>&lga=<?php echo $lga_id; ?>&state=<?php echo $state_id; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&severity=<?php echo urlencode($severity_filter); ?>&type=<?php echo urlencode($type_filter); ?>" 
                           class="btn-page <?php echo $i == $page ? 'active' : ''; ?>" 
                           style="padding:6px 12px;border:1px solid <?php echo $i == $page ? 'var(--primary)' : 'var(--gray-200)'; ?>;border-radius:8px;text-decoration:none;color:<?php echo $i == $page ? 'white' : 'var(--gray-600)'; ?>;font-size:0.8rem;transition:var(--transition);background:<?php echo $i == $page ? 'var(--primary)' : 'transparent'; ?>;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; 
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span style="padding:6px 8px;color:var(--gray-400);">...</span>';
                        echo '<a href="?page=' . $total_pages . '&lga=' . $lga_id . '&state=' . $state_id . '&search=' . urlencode($search) . '&status=' . urlencode($status_filter) . '&severity=' . urlencode($severity_filter) . '&type=' . urlencode($type_filter) . '" class="btn-page" style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&lga=<?php echo $lga_id; ?>&state=<?php echo $state_id; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&severity=<?php echo urlencode($severity_filter); ?>&type=<?php echo urlencode($type_filter); ?>" 
                           class="btn-page" style="padding:6px 14px;border:1px solid var(--gray-200);border-radius:8px;text-decoration:none;color:var(--gray-600);font-size:0.8rem;transition:var(--transition);">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-top:8px;">
            <a href="incident-create.php" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-plus-circle" style="color:var(--danger);"></i>
                <span>Report New Incident</span>
            </a>
            <a href="incident-dashboard.php" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-chart-bar" style="color:var(--primary);"></i>
                <span>Incident Analytics</span>
            </a>
            <a href="incident-types.php" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-tags" style="color:var(--secondary);"></i>
                <span>Incident Types</span>
            </a>
            <a href="reports.php?type=incident" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-file-alt" style="color:var(--warning);"></i>
                <span>Generate Report</span>
            </a>
        </div>
    </div>
</main>

<style>
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.stat-icon.red { background: #FEF2F2; color: #EF4444; }
.stat-icon.yellow { background: #FFFBEB; color: #F59E0B; }
.stat-icon.green { background: #ECFDF5; color: #10B981; }
.stat-icon.purple { background: #F5F3FF; color: #8B5CF6; }
.stat-icon.blue { background: #EFF6FF; color: #3B82F6; }

.btn-sm:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.quick-action-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
.btn-secondary:hover { background: var(--gray-200); transform: translateY(-1px); }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
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
</html>a