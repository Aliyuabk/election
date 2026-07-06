<?php
// ============================================================
// NATIONAL COORDINATOR - VIEW STATE DETAILS
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

// Get state ID from URL
$state_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// If no state ID or invalid, redirect to monitor-states
if ($state_id <= 0) {
    header('Location: monitor-states.php?error=invalid_state');
    exit();
}

$db = getDB();

// ============================================================
// FIRST - Check if state exists
// ============================================================
$state_exists = false;
try {
    $stmt = $db->prepare("SELECT id, name, capital, is_active FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state_check = $stmt->fetch();
    if ($state_check) {
        $state_exists = true;
        $state_data = $state_check;
    }
} catch (Exception $e) {
    error_log("View State Check Error: " . $e->getMessage());
    header('Location: monitor-states.php?error=database_error');
    exit();
}

if (!$state_exists) {
    header('Location: monitor-states.php?error=state_not_found');
    exit();
}

// ============================================================
// FETCH FULL STATE DATA WITH STATISTICS - Simplified queries
// ============================================================
try {
    // Get state data
    $stmt = $db->prepare("SELECT * FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state_data = $stmt->fetch();
    
    if (!$state_data) {
        header('Location: monitor-states.php?error=state_not_found');
        exit();
    }
    
    // Get LGAs count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lgas WHERE state_id = ? AND is_active = 1");
    $stmt->execute([$state_id]);
    $lga_count = $stmt->fetch();
    $state_data['total_lgas'] = $lga_count['count'] ?? 0;
    $state_data['active_lgas'] = $lga_count['count'] ?? 0;
    
    // Get Wards count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM wards w JOIN lgas l ON w.lga_id = l.id WHERE l.state_id = ? AND w.is_active = 1");
    $stmt->execute([$state_id]);
    $ward_count = $stmt->fetch();
    $state_data['total_wards'] = $ward_count['count'] ?? 0;
    
    // Get Polling Units count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM polling_units pu JOIN wards w ON pu.ward_id = w.id JOIN lgas l ON w.lga_id = l.id WHERE l.state_id = ? AND pu.is_active = 1");
    $stmt->execute([$state_id]);
    $pu_count = $stmt->fetch();
    $state_data['total_pus'] = $pu_count['count'] ?? 0;
    
    // Get total voters
    $stmt = $db->prepare("SELECT COALESCE(SUM(registered_voters), 0) as total FROM polling_units pu JOIN wards w ON pu.ward_id = w.id JOIN lgas l ON w.lga_id = l.id WHERE l.state_id = ?");
    $stmt->execute([$state_id]);
    $voters = $stmt->fetch();
    $state_data['total_voters'] = $voters['total'] ?? 0;
    
    // Get State Coordinators
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'state' AND u.jurisdiction_id = ? AND u.status = 'active' AND u.deleted_at IS NULL");
    $stmt->execute([$tenant_id, $state_id]);
    $coords = $stmt->fetch();
    $state_data['coordinators'] = $coords['count'] ?? 0;
    
    // Get LGA Coordinators
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'lga' AND u.jurisdiction_id IN (SELECT id FROM lgas WHERE state_id = ?) AND u.status = 'active' AND u.deleted_at IS NULL");
    $stmt->execute([$tenant_id, $state_id]);
    $lga_coords = $stmt->fetch();
    $state_data['lga_coordinators'] = $lga_coords['count'] ?? 0;
    
    // Get Agents
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'pu_agent' AND u.jurisdiction_id IN (SELECT id FROM polling_units WHERE ward_id IN (SELECT id FROM wards WHERE lga_id IN (SELECT id FROM lgas WHERE state_id = ?))) AND u.status = 'active' AND u.deleted_at IS NULL");
    $stmt->execute([$tenant_id, $state_id]);
    $agents = $stmt->fetch();
    $state_data['agents'] = $agents['count'] ?? 0;
    
    // Get Elections count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM elections WHERE tenant_id = ? AND deleted_at IS NULL AND JSON_CONTAINS(states_json, JSON_QUOTE(?))");
    $stmt->execute([$tenant_id, $state_id]);
    $elections = $stmt->fetch();
    $state_data['election_count'] = $elections['count'] ?? 0;
    
    // Get Active Elections
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM elections WHERE tenant_id = ? AND deleted_at IS NULL AND status = 'active' AND JSON_CONTAINS(states_json, JSON_QUOTE(?))");
    $stmt->execute([$tenant_id, $state_id]);
    $active_elections = $stmt->fetch();
    $state_data['active_elections'] = $active_elections['count'] ?? 0;
    
    // Get Total Results
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM results_ec8a r JOIN polling_units pu ON r.pu_id = pu.id JOIN wards w ON pu.ward_id = w.id JOIN lgas l ON w.lga_id = l.id WHERE r.tenant_id = ? AND l.state_id = ?");
    $stmt->execute([$tenant_id, $state_id]);
    $total_results = $stmt->fetch();
    $state_data['total_results'] = $total_results['count'] ?? 0;
    
    // Get Verified Results
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM results_ec8a r JOIN polling_units pu ON r.pu_id = pu.id JOIN wards w ON pu.ward_id = w.id JOIN lgas l ON w.lga_id = l.id WHERE r.tenant_id = ? AND l.state_id = ? AND r.status = 'verified'");
    $stmt->execute([$tenant_id, $state_id]);
    $verified_results = $stmt->fetch();
    $state_data['verified_results'] = $verified_results['count'] ?? 0;
    
    // Get Pending Results
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM results_ec8a r JOIN polling_units pu ON r.pu_id = pu.id JOIN wards w ON pu.ward_id = w.id JOIN lgas l ON w.lga_id = l.id WHERE r.tenant_id = ? AND l.state_id = ? AND r.status = 'pending'");
    $stmt->execute([$tenant_id, $state_id]);
    $pending_results = $stmt->fetch();
    $state_data['pending_results'] = $pending_results['count'] ?? 0;
    
    // Get Flagged Results
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM results_ec8a r JOIN polling_units pu ON r.pu_id = pu.id JOIN wards w ON pu.ward_id = w.id JOIN lgas l ON w.lga_id = l.id WHERE r.tenant_id = ? AND l.state_id = ? AND r.status = 'flagged'");
    $stmt->execute([$tenant_id, $state_id]);
    $flagged_results = $stmt->fetch();
    $state_data['flagged_results'] = $flagged_results['count'] ?? 0;
    
    // Get Total Incidents
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM incidents WHERE tenant_id = ? AND state_id = ?");
    $stmt->execute([$tenant_id, $state_id]);
    $total_incidents = $stmt->fetch();
    $state_data['total_incidents'] = $total_incidents['count'] ?? 0;
    
    // Get Reported Incidents
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM incidents WHERE tenant_id = ? AND state_id = ? AND status = 'reported'");
    $stmt->execute([$tenant_id, $state_id]);
    $reported_incidents = $stmt->fetch();
    $state_data['reported_incidents'] = $reported_incidents['count'] ?? 0;
    
    // Get Investigating Incidents
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM incidents WHERE tenant_id = ? AND state_id = ? AND status = 'investigating'");
    $stmt->execute([$tenant_id, $state_id]);
    $investigating_incidents = $stmt->fetch();
    $state_data['investigating_incidents'] = $investigating_incidents['count'] ?? 0;
    
    // Get Resolved Incidents
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM incidents WHERE tenant_id = ? AND state_id = ? AND status = 'resolved'");
    $stmt->execute([$tenant_id, $state_id]);
    $resolved_incidents = $stmt->fetch();
    $state_data['resolved_incidents'] = $resolved_incidents['count'] ?? 0;
    
    // Get Critical Incidents
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM incidents WHERE tenant_id = ? AND state_id = ? AND severity = 'critical'");
    $stmt->execute([$tenant_id, $state_id]);
    $critical_incidents = $stmt->fetch();
    $state_data['critical_incidents'] = $critical_incidents['count'] ?? 0;
    
} catch (Exception $e) {
    error_log("View State Data Error: " . $e->getMessage());
    header('Location: monitor-states.php?error=database_error');
    exit();
}

// ============================================================
// FETCH RECENT ACTIVITIES
// ============================================================
$recent_activities = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, u.full_name as user_name
        FROM activity_logs a
        JOIN users u ON a.user_id = u.id
        WHERE a.tenant_id = ? 
        ORDER BY a.created_at DESC
        LIMIT 15
    ");
    $stmt->execute([$tenant_id]);
    $recent_activities = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_activities = [];
}

// ============================================================
// FETCH TOP LGAs BY RESULTS
// ============================================================
$top_lgas = [];
try {
    $stmt = $db->prepare("
        SELECT 
            l.id,
            l.name,
            COUNT(r.id) as result_count,
            SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified_count
        FROM lgas l
        LEFT JOIN polling_units pu ON pu.ward_id IN (SELECT id FROM wards WHERE lga_id = l.id)
        LEFT JOIN results_ec8a r ON r.pu_id = pu.id AND r.tenant_id = ?
        WHERE l.state_id = ?
        GROUP BY l.id
        ORDER BY verified_count DESC
        LIMIT 10
    ");
    $stmt->execute([$tenant_id, $state_id]);
    $top_lgas = $stmt->fetchAll();
} catch (Exception $e) {
    $top_lgas = [];
}

// ============================================================
// CALCULATE PROGRESS
// ============================================================
$total_pus = $state_data['total_pus'] ?? 0;
$verified_results = $state_data['verified_results'] ?? 0;
$progress_percent = $total_pus > 0 ? min(100, round(($verified_results / $total_pus) * 100)) : 0;

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'State Details';
$page_subtitle = $state_data['name'] ?? 'State';
?>
<!-- Rest of the HTML remains the same -->

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Breadcrumb -->
        <div class="welcome-section">
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <a href="../national/index.php" style="text-decoration:none;color:var(--gray-500);">Dashboard</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="monitor-states.php" style="text-decoration:none;color:var(--gray-500);">Monitor States</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);"><?php echo htmlspecialchars($state_data['name'] ?? 'State'); ?></span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;display:flex;align-items:center;gap:12px;">
                        <span><?php echo htmlspecialchars($state_data['name'] ?? 'State'); ?></span>
                        <span style="font-size:0.7rem;background:<?php echo ($state_data['is_active'] ?? 0) ? 'var(--primary)' : '#6B7280'; ?>;color:white;padding:2px 12px;border-radius:20px;font-weight:500;">
                            <?php echo ($state_data['is_active'] ?? 0) ? 'Active' : 'Inactive'; ?>
                        </span>
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-city"></i> Capital: <?php echo htmlspecialchars($state_data['capital'] ?? 'N/A'); ?>
                        <?php if (($state_data['total_voters'] ?? 0) > 0): ?>
                            • <i class="fas fa-users"></i> <?php echo number_format($state_data['total_voters'] ?? 0); ?> registered voters
                        <?php endif; ?>
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="state-dashboard.php?id=<?php echo $state_data['id']; ?>" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="state-coordinators.php?id=<?php echo $state_data['id']; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-user-tie"></i> Coordinators
                    </a>
                    <a href="state-results.php?id=<?php echo $state_data['id']; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-file-alt"></i> Results
                    </a>
                </div>
            </div>
        </div>

        <!-- Progress Overview -->
        <div style="background:white;border-radius:var(--radius);padding:20px;margin-bottom:20px;border:1px solid var(--gray-200);">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:12px;">
                <div>
                    <h3 style="font-size:0.9rem;font-weight:600;margin:0;">Election Progress</h3>
                    <p style="font-size:0.75rem;color:var(--gray-500);margin:2px 0 0;">
                        <?php echo number_format($state_data['verified_results'] ?? 0); ?> of <?php echo number_format($state_data['total_pus'] ?? 0); ?> polling units verified
                    </p>
                </div>
                <span style="font-size:1.2rem;font-weight:700;color:<?php echo $progress_percent >= 80 ? '#10B981' : ($progress_percent >= 50 ? '#F59E0B' : '#EF4444'); ?>;">
                    <?php echo $progress_percent; ?>%
                </span>
            </div>
            <div style="width:100%;height:12px;background:var(--gray-100);border-radius:8px;overflow:hidden;">
                <div style="width:<?php echo $progress_percent; ?>%;height:100%;background:linear-gradient(90deg, <?php echo $progress_percent >= 80 ? '#10B981' : ($progress_percent >= 50 ? '#F59E0B' : '#EF4444'); ?>, <?php echo $progress_percent >= 80 ? '#34D399' : ($progress_percent >= 50 ? '#FBBF24' : '#F87171'); ?>);border-radius:8px;transition:width 1s ease;"></div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-map-marker-alt"></i></div>
                <div class="stat-number"><?php echo number_format($state_data['total_lgas'] ?? 0); ?></div>
                <div class="stat-label">LGAs</div>
                <div class="stat-change"><i class="fas fa-check-circle"></i> <?php echo $state_data['active_lgas'] ?? 0; ?> active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-layer-group"></i></div>
                <div class="stat-number"><?php echo number_format($state_data['total_wards'] ?? 0); ?></div>
                <div class="stat-label">Wards</div>
                <div class="stat-change"><i class="fas fa-flag"></i> Total</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($state_data['total_pus'] ?? 0); ?></div>
                <div class="stat-label">Polling Units</div>
                <div class="stat-change"><i class="fas fa-users"></i> <?php echo number_format($state_data['agents'] ?? 0); ?> agents</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format($state_data['coordinators'] ?? 0); ?></div>
                <div class="stat-label">State Coordinators</div>
                <div class="stat-change"><i class="fas fa-users"></i> <?php echo $state_data['lga_coordinators'] ?? 0; ?> LGA coordinators</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number"><?php echo number_format($state_data['verified_results'] ?? 0); ?></div>
                <div class="stat-label">Verified Results</div>
                <div class="stat-change down"><i class="fas fa-clock"></i> <?php echo $state_data['pending_results'] ?? 0; ?> pending</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($state_data['total_incidents'] ?? 0); ?></div>
                <div class="stat-label">Incidents</div>
                <div class="stat-change down"><i class="fas fa-clock"></i> <?php echo $state_data['reported_incidents'] ?? 0; ?> open</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-vote-yea"></i></div>
                <div class="stat-number"><?php echo number_format($state_data['election_count'] ?? 0); ?></div>
                <div class="stat-label">Elections</div>
                <div class="stat-change up"><i class="fas fa-play"></i> <?php echo $state_data['active_elections'] ?? 0; ?> active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pink"><i class="fas fa-user"></i></div>
                <div class="stat-number"><?php echo number_format($state_data['total_voters'] ?? 0); ?></div>
                <div class="stat-label">Registered Voters</div>
                <div class="stat-change"><i class="fas fa-address-card"></i> Total</div>
            </div>
        </div>

        <!-- Incident Summary -->
        <?php if (($state_data['total_incidents'] ?? 0) > 0): ?>
        <div style="background:white;border-radius:var(--radius);padding:16px 20px;margin-bottom:20px;border:1px solid var(--gray-200);">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <div>
                    <h4 style="font-size:0.85rem;font-weight:600;margin:0;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-exclamation-triangle" style="color:var(--danger);"></i>
                        Incident Summary
                    </h4>
                </div>
                <div style="display:flex;gap:16px;flex-wrap:wrap;">
                    <div style="display:flex;align-items:center;gap:6px;font-size:0.8rem;">
                        <span class="badge badge-danger"><?php echo $state_data['critical_incidents'] ?? 0; ?></span>
                        <span style="color:var(--gray-500);">Critical</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;font-size:0.8rem;">
                        <span class="badge badge-warning"><?php echo $state_data['reported_incidents'] ?? 0; ?></span>
                        <span style="color:var(--gray-500);">Reported</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;font-size:0.8rem;">
                        <span class="badge badge-info"><?php echo $state_data['investigating_incidents'] ?? 0; ?></span>
                        <span style="color:var(--gray-500);">Investigating</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;font-size:0.8rem;">
                        <span class="badge badge-success"><?php echo $state_data['resolved_incidents'] ?? 0; ?></span>
                        <span style="color:var(--gray-500);">Resolved</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Two Column Layout -->
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px;">
            <!-- Left Column: Top LGAs -->
            <div style="background:white;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h4 style="font-size:0.85rem;font-weight:600;margin:0;">
                        <i class="fas fa-chart-bar" style="color:var(--primary);margin-right:6px;"></i>
                        Top Performing LGAs
                    </h4>
                    <a href="state-dashboard.php?id=<?php echo $state_data['id']; ?>" style="font-size:0.7rem;color:var(--primary);text-decoration:none;">View All →</a>
                </div>
                <?php if (count($top_lgas) > 0): ?>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <?php foreach ($top_lgas as $index => $lga): 
                            $lga_progress = ($lga['result_count'] ?? 0) > 0 ? min(100, round(($lga['verified_count'] ?? 0) / max(1, $lga['result_count']) * 100)) : 0;
                            $colors = ['#3B82F6', '#10B981', '#8B5CF6', '#F59E0B', '#EF4444', '#EC4899', '#14B8A6', '#F97316', '#6366F1', '#84CC16'];
                            $color = $colors[$index % count($colors)];
                        ?>
                            <div style="display:flex;align-items:center;gap:12px;">
                                <span style="font-weight:600;font-size:0.7rem;color:var(--gray-400);width:20px;">#<?php echo $index + 1; ?></span>
                                <div style="flex:1;">
                                    <div style="display:flex;justify-content:space-between;font-size:0.8rem;">
                                        <span style="font-weight:500;"><?php echo htmlspecialchars($lga['name']); ?></span>
                                        <span style="font-weight:600;color:<?php echo $color; ?>;"><?php echo $lga['verified_count'] ?? 0; ?> verified</span>
                                    </div>
                                    <div style="width:100%;height:4px;background:var(--gray-100);border-radius:4px;overflow:hidden;margin-top:2px;">
                                        <div style="width:<?php echo $lga_progress; ?>%;height:100%;background:<?php echo $color; ?>;border-radius:4px;"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color:var(--gray-500);text-align:center;padding:20px 0;">No LGA data available</p>
                <?php endif; ?>
            </div>

            <!-- Right Column: Recent Activities -->
            <div style="background:white;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h4 style="font-size:0.85rem;font-weight:600;margin:0;">
                        <i class="fas fa-clock" style="color:var(--primary);margin-right:6px;"></i>
                        Recent Activities
                    </h4>
                    <a href="activity-logs.php?state=<?php echo $state_data['id']; ?>" style="font-size:0.7rem;color:var(--primary);text-decoration:none;">View All →</a>
                </div>
                <?php if (count($recent_activities) > 0): ?>
                    <div style="max-height:400px;overflow-y:auto;">
                        <?php foreach (array_slice($recent_activities, 0, 10) as $activity): ?>
                            <div style="display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid var(--gray-100);">
                                <div style="width:28px;height:28px;border-radius:50%;background:<?php echo strpos($activity['activity_type'] ?? '', 'login') !== false ? '#EFF6FF' : '#F1F5F9'; ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:<?php echo strpos($activity['activity_type'] ?? '', 'login') !== false ? '#3B82F6' : '#64748B'; ?>;">
                                    <i class="fas <?php echo strpos($activity['activity_type'] ?? '', 'login') !== false ? 'fa-sign-in-alt' : 'fa-cog'; ?>" style="font-size:0.6rem;"></i>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:0.75rem;font-weight:500;color:var(--gray-700);"><?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></div>
                                    <div style="font-size:0.7rem;color:var(--gray-500);"><?php echo htmlspecialchars($activity['description'] ?? ''); ?></div>
                                    <div style="font-size:0.6rem;color:var(--gray-400);"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'] ?? 'now')); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color:var(--gray-500);text-align:center;padding:20px 0;">No recent activities</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
            <a href="state-coordinators.php?id=<?php echo $state_data['id']; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-user-tie" style="color:var(--secondary);"></i>
                <span>Manage Coordinators</span>
            </a>
            <a href="broadcasts-create.php?state=<?php echo $state_data['id']; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-bullhorn" style="color:var(--warning);"></i>
                <span>Broadcast to State</span>
            </a>
            <a href="reports.php?type=state&id=<?php echo $state_data['id']; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-file-alt" style="color:var(--danger);"></i>
                <span>Generate Report</span>
            </a>
            <a href="state-comparison.php" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-chart-bar" style="color:var(--primary);"></i>
                <span>Compare States</span>
            </a>
        </div>
    </div>
</main>

<style>
.badge-success { background: #D1FAE5; color: #065F46; padding: 2px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; }
.badge-danger { background: #FEE2E2; color: #991B1B; padding: 2px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; }
.badge-warning { background: #FEF3C7; color: #92400E; padding: 2px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; }
.badge-info { background: #DBEAFE; color: #1E40AF; padding: 2px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; }

.stat-icon.teal { background: #CCFBF1; color: #0D9488; }
.stat-icon.pink { background: #FCE7F3; color: #DB2777; }

.btn-secondary:hover {
    background: var(--gray-200);
    border-color: var(--gray-300);
}

.quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
    border-color: var(--primary);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    div[style*="grid-template-columns:2fr 1fr"] {
        grid-template-columns: 1fr !important;
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