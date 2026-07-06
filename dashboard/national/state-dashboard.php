<?php
// ============================================================
// NATIONAL COORDINATOR - STATE DASHBOARD VIEW
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
// FETCH STATE DATA - Check if state exists
// ============================================================
$state_data = null;
try {
    $stmt = $db->prepare("SELECT id, name, capital, is_active FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If state doesn't exist, redirect
    if (!$state_data) {
        header('Location: monitor-states.php?error=state_not_found');
        exit();
    }
} catch (Exception $e) {
    error_log("State Dashboard Error: " . $e->getMessage());
    header('Location: monitor-states.php?error=database_error');
    exit();
}

// ============================================================
// FETCH FULL STATE DATA WITH STATISTICS
// ============================================================
try {
    // Get LGAs count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lgas WHERE state_id = ? AND is_active = 1");
    $stmt->execute([$state_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_data['total_lgas'] = $result['count'] ?? 0;
    
    // Get Wards count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM wards w JOIN lgas l ON w.lga_id = l.id WHERE l.state_id = ? AND w.is_active = 1");
    $stmt->execute([$state_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_data['total_wards'] = $result['count'] ?? 0;
    
    // Get Polling Units count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM polling_units pu JOIN wards w ON pu.ward_id = w.id JOIN lgas l ON w.lga_id = l.id WHERE l.state_id = ? AND pu.is_active = 1");
    $stmt->execute([$state_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_data['total_pus'] = $result['count'] ?? 0;
    
    // Get State Coordinators
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'state' AND u.jurisdiction_id = ? AND u.status = 'active' AND (u.deleted_at IS NULL OR u.deleted_at = '0000-00-00 00:00:00')");
    $stmt->execute([$tenant_id, $state_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_data['coordinators'] = $result['count'] ?? 0;
    
    // Get LGA Coordinators
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'lga' AND u.jurisdiction_id IN (SELECT id FROM lgas WHERE state_id = ?) AND u.status = 'active' AND (u.deleted_at IS NULL OR u.deleted_at = '0000-00-00 00:00:00')");
    $stmt->execute([$tenant_id, $state_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_data['lga_coordinators'] = $result['count'] ?? 0;
    
    // Get Agents
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'pu_agent' AND u.jurisdiction_id IN (SELECT id FROM polling_units WHERE ward_id IN (SELECT id FROM wards WHERE lga_id IN (SELECT id FROM lgas WHERE state_id = ?))) AND u.status = 'active' AND (u.deleted_at IS NULL OR u.deleted_at = '0000-00-00 00:00:00')");
    $stmt->execute([$tenant_id, $state_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_data['agents'] = $result['count'] ?? 0;
    
    // Get Elections count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM elections WHERE tenant_id = ? AND deleted_at IS NULL AND states_json IS NOT NULL AND states_json != '' AND JSON_CONTAINS(states_json, JSON_QUOTE(?))");
    $stmt->execute([$tenant_id, $state_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_data['election_count'] = $result['count'] ?? 0;
    
    // Get Active Elections
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM elections WHERE tenant_id = ? AND deleted_at IS NULL AND status = 'active' AND states_json IS NOT NULL AND states_json != '' AND JSON_CONTAINS(states_json, JSON_QUOTE(?))");
    $stmt->execute([$tenant_id, $state_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_data['active_elections'] = $result['count'] ?? 0;
    
    // Get Upcoming Elections
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM elections WHERE tenant_id = ? AND deleted_at IS NULL AND status = 'upcoming' AND states_json IS NOT NULL AND states_json != '' AND JSON_CONTAINS(states_json, JSON_QUOTE(?))");
    $stmt->execute([$tenant_id, $state_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_data['upcoming_elections'] = $result['count'] ?? 0;
    
    // Get Completed Elections
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM elections WHERE tenant_id = ? AND deleted_at IS NULL AND status = 'completed' AND states_json IS NOT NULL AND states_json != '' AND JSON_CONTAINS(states_json, JSON_QUOTE(?))");
    $stmt->execute([$tenant_id, $state_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_data['completed_elections'] = $result['count'] ?? 0;
    
    // Get Total Results
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM results_ec8a r JOIN polling_units pu ON r.pu_id = pu.id JOIN wards w ON pu.ward_id = w.id JOIN lgas l ON w.lga_id = l.id WHERE r.tenant_id = ? AND l.state_id = ?");
    $stmt->execute([$tenant_id, $state_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_data['total_results'] = $result['count'] ?? 0;
    
    // Get Verified Results
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM results_ec8a r JOIN polling_units pu ON r.pu_id = pu.id JOIN wards w ON pu.ward_id = w.id JOIN lgas l ON w.lga_id = l.id WHERE r.tenant_id = ? AND l.state_id = ? AND r.status = 'verified'");
    $stmt->execute([$tenant_id, $state_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_data['verified_results'] = $result['count'] ?? 0;
    
    // Get Pending Results
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM results_ec8a r JOIN polling_units pu ON r.pu_id = pu.id JOIN wards w ON pu.ward_id = w.id JOIN lgas l ON w.lga_id = l.id WHERE r.tenant_id = ? AND l.state_id = ? AND r.status = 'pending'");
    $stmt->execute([$tenant_id, $state_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_data['pending_results'] = $result['count'] ?? 0;
    
    // Get Flagged Results
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM results_ec8a r JOIN polling_units pu ON r.pu_id = pu.id JOIN wards w ON pu.ward_id = w.id JOIN lgas l ON w.lga_id = l.id WHERE r.tenant_id = ? AND l.state_id = ? AND r.status = 'flagged'");
    $stmt->execute([$tenant_id, $state_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_data['flagged_results'] = $result['count'] ?? 0;
    
    // Get Total Incidents
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM incidents WHERE tenant_id = ? AND state_id = ?");
    $stmt->execute([$tenant_id, $state_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_data['total_incidents'] = $result['count'] ?? 0;
    
    // Get Open Incidents
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM incidents WHERE tenant_id = ? AND state_id = ? AND status IN ('reported', 'investigating')");
    $stmt->execute([$tenant_id, $state_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_data['open_incidents'] = $result['count'] ?? 0;
    
    // Get Resolved Incidents
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM incidents WHERE tenant_id = ? AND state_id = ? AND status = 'resolved'");
    $stmt->execute([$tenant_id, $state_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_data['resolved_incidents'] = $result['count'] ?? 0;
    
    // Get Critical Incidents
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM incidents WHERE tenant_id = ? AND state_id = ? AND severity = 'critical'");
    $stmt->execute([$tenant_id, $state_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_data['critical_incidents'] = $result['count'] ?? 0;
    
    // Get Active Broadcasts
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM broadcasts WHERE tenant_id = ? AND status IN ('scheduled', 'sending')");
    $stmt->execute([$tenant_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_data['active_broadcasts'] = $result['count'] ?? 0;
    
    // Get Active Assignments
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM agent_assignments WHERE tenant_id = ? AND state_id = ? AND status = 'active'");
    $stmt->execute([$tenant_id, $state_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_data['active_assignments'] = $result['count'] ?? 0;
    
} catch (Exception $e) {
    error_log("State Dashboard Data Error: " . $e->getMessage());
    header('Location: monitor-states.php?error=database_error');
    exit();
}

// ============================================================
// FETCH LGA DATA WITH PROGRESS
// ============================================================
$lgas = [];
try {
    $stmt = $db->prepare("
        SELECT 
            l.id,
            l.name,
            l.registered_voters,
            (SELECT COUNT(*) FROM wards WHERE lga_id = l.id AND is_active = 1) as ward_count,
            (SELECT COUNT(*) FROM polling_units pu WHERE pu.ward_id IN (SELECT id FROM wards WHERE lga_id = l.id) AND pu.is_active = 1) as pu_count,
            (SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'lga' AND u.jurisdiction_id = l.id AND u.status = 'active' AND (u.deleted_at IS NULL OR u.deleted_at = '0000-00-00 00:00:00')) as coordinators,
            (SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'pu_agent' AND u.jurisdiction_id IN (SELECT id FROM polling_units WHERE ward_id IN (SELECT id FROM wards WHERE lga_id = l.id)) AND u.status = 'active' AND (u.deleted_at IS NULL OR u.deleted_at = '0000-00-00 00:00:00')) as agents,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.tenant_id = ? AND r.lga_id = l.id) as total_results,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.tenant_id = ? AND r.lga_id = l.id AND r.status = 'verified') as verified_results,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.tenant_id = ? AND r.lga_id = l.id AND r.status = 'pending') as pending_results,
            (SELECT COUNT(*) FROM incidents i WHERE i.tenant_id = ? AND i.lga_id = l.id) as incidents
        FROM lgas l
        WHERE l.state_id = ? AND l.is_active = 1
        ORDER BY l.name ASC
    ");
    $stmt->execute([$tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $state_id]);
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lgas = [];
}

// ============================================================
// CALCULATE PROGRESS
// ============================================================
$total_pus = $state_data['total_pus'] ?? 0;
$verified_results = $state_data['verified_results'] ?? 0;
$progress_percent = $total_pus > 0 ? min(100, round(($verified_results / $total_pus) * 100)) : 0;

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'State Dashboard';
$page_subtitle = $state_data['name'] ?? 'State';
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
                <a href="monitor-states.php" style="text-decoration:none;color:var(--gray-500);">Monitor States</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="view-state.php?id=<?php echo $state_data['id']; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($state_data['name'] ?? 'State'); ?></a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Dashboard</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <?php echo htmlspecialchars($state_data['name'] ?? 'State'); ?> Dashboard
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-flag"></i> Capital: <?php echo htmlspecialchars($state_data['capital'] ?? 'N/A'); ?>
                        • <?php echo number_format($state_data['total_pus'] ?? 0); ?> Polling Units
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="view-state.php?id=<?php echo $state_data['id']; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <a href="state-coordinators.php?id=<?php echo $state_data['id']; ?>" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-user-tie"></i> Coordinators
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-map-marker-alt"></i></div>
                <div class="stat-number"><?php echo number_format($state_data['total_lgas'] ?? 0); ?></div>
                <div class="stat-label">Total LGAs</div>
                <div class="stat-change"><i class="fas fa-user-tie"></i> <?php echo $state_data['lga_coordinators'] ?? 0; ?> coordinators</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-layer-group"></i></div>
                <div class="stat-number"><?php echo number_format($state_data['total_wards'] ?? 0); ?></div>
                <div class="stat-label">Total Wards</div>
                <div class="stat-change"><i class="fas fa-flag"></i> Full coverage</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($state_data['total_pus'] ?? 0); ?></div>
                <div class="stat-label">Polling Units</div>
                <div class="stat-change"><i class="fas fa-users"></i> <?php echo number_format($state_data['agents'] ?? 0); ?> agents</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-check-double"></i></div>
                <div class="stat-number"><?php echo number_format($state_data['verified_results'] ?? 0); ?></div>
                <div class="stat-label">Verified Results</div>
                <div class="stat-change down"><i class="fas fa-clock"></i> <?php echo $state_data['pending_results'] ?? 0; ?> pending</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($state_data['open_incidents'] ?? 0); ?></div>
                <div class="stat-label">Open Incidents</div>
                <div class="stat-change down"><i class="fas fa-clock"></i> <?php echo $state_data['critical_incidents'] ?? 0; ?> critical</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-vote-yea"></i></div>
                <div class="stat-number"><?php echo number_format($state_data['election_count'] ?? 0); ?></div>
                <div class="stat-label">Elections</div>
                <div class="stat-change up"><i class="fas fa-play"></i> <?php echo $state_data['active_elections'] ?? 0; ?> active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-bullhorn"></i></div>
                <div class="stat-number"><?php echo number_format($state_data['active_broadcasts'] ?? 0); ?></div>
                <div class="stat-label">Active Broadcasts</div>
                <div class="stat-change"><i class="fas fa-clock"></i> Scheduled</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pink"><i class="fas fa-clipboard-list"></i></div>
                <div class="stat-number"><?php echo number_format($state_data['active_assignments'] ?? 0); ?></div>
                <div class="stat-label">Active Assignments</div>
                <div class="stat-change"><i class="fas fa-user-check"></i> Agents</div>
            </div>
        </div>

        <!-- Progress Overview -->
        <div style="background:white;border-radius:var(--radius);padding:20px;margin-bottom:20px;border:1px solid var(--gray-200);">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:12px;">
                <div>
                    <h3 style="font-size:0.9rem;font-weight:600;margin:0;">Overall Election Progress</h3>
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
            <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:0.65rem;color:var(--gray-400);">
                <span>0%</span>
                <span>50%</span>
                <span>100%</span>
            </div>
        </div>

        <!-- LGAs Table -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0;">
                    <i class="fas fa-map-marker-alt" style="color:var(--primary);margin-right:6px;"></i>
                    LGAs in <?php echo htmlspecialchars($state_data['name'] ?? 'State'); ?>
                </h4>
                <span style="font-size:0.75rem;color:var(--gray-500);"><?php echo count($lgas); ?> LGAs</span>
            </div>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                    <thead style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
                        <tr>
                            <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--gray-600);">LGA</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Wards</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">PUs</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Coordinators</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Agents</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Results</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Progress</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Incidents</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($lgas) > 0): ?>
                            <?php foreach ($lgas as $lga): 
                                $pu_count = $lga['pu_count'] ?? 0;
                                $verified_results = $lga['verified_results'] ?? 0;
                                $lga_progress = $pu_count > 0 ? min(100, round(($verified_results / $pu_count) * 100)) : 0;
                                $progress_color = $lga_progress >= 80 ? '#10B981' : ($lga_progress >= 50 ? '#F59E0B' : '#EF4444');
                            ?>
                                <tr style="border-bottom:1px solid var(--gray-100);transition:var(--transition);hover:background:var(--gray-50);">
                                    <td style="padding:10px 14px;font-weight:500;"><?php echo htmlspecialchars($lga['name']); ?></td>
                                    <td style="padding:10px 14px;text-align:center;"><?php echo number_format($lga['ward_count'] ?? 0); ?></td>
                                    <td style="padding:10px 14px;text-align:center;"><?php echo number_format($pu_count); ?></td>
                                    <td style="padding:10px 14px;text-align:center;color:var(--primary);"><?php echo number_format($lga['coordinators'] ?? 0); ?></td>
                                    <td style="padding:10px 14px;text-align:center;color:var(--secondary);"><?php echo number_format($lga['agents'] ?? 0); ?></td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <span style="font-weight:600;"><?php echo number_format($verified_results); ?></span>
                                        <span style="font-size:0.6rem;color:var(--gray-400);">/ <?php echo number_format($lga['total_results'] ?? 0); ?></span>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <div style="display:flex;align-items:center;gap:6px;justify-content:center;">
                                            <div style="width:50px;height:4px;background:var(--gray-200);border-radius:4px;overflow:hidden;">
                                                <div style="width:<?php echo $lga_progress; ?>%;height:100%;background:<?php echo $progress_color; ?>;border-radius:4px;"></div>
                                            </div>
                                            <span style="font-size:0.6rem;font-weight:600;color:<?php echo $progress_color; ?>;"><?php echo $lga_progress; ?>%</span>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <?php if (($lga['incidents'] ?? 0) > 0): ?>
                                            <span style="color:var(--danger);font-weight:600;"><?php echo number_format($lga['incidents'] ?? 0); ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);font-size:0.7rem;">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <a href="lga-dashboard.php?id=<?php echo $lga['id']; ?>" class="btn-sm" style="padding:4px 10px;border-radius:6px;background:var(--primary);color:white;text-decoration:none;font-size:0.65rem;transition:var(--transition);">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="padding:30px;text-align:center;color:var(--gray-500);">
                                    <i class="fas fa-map-marker-alt" style="font-size:1.5rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                                    No LGAs found in this state
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Actions -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
            <a href="state-coordinators.php?id=<?php echo $state_data['id']; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-user-tie" style="color:var(--secondary);"></i>
                <span>Manage Coordinators</span>
            </a>
            <a href="state-results.php?id=<?php echo $state_data['id']; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-file-alt" style="color:var(--warning);"></i>
                <span>View All Results</span>
            </a>
            <a href="broadcasts-create.php?state=<?php echo $state_data['id']; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-bullhorn" style="color:var(--primary);"></i>
                <span>Broadcast to State</span>
            </a>
            <a href="reports.php?type=state&id=<?php echo $state_data['id']; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-file-pdf" style="color:var(--danger);"></i>
                <span>Generate Report</span>
            </a>
        </div>
    </div>
</main>

<style>
.btn-sm:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.quick-action-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
.stat-icon.teal { background: #CCFBF1; color: #0D9488; }
.stat-icon.pink { background: #FCE7F3; color: #DB2777; }
.btn-secondary:hover { background: var(--gray-200); transform: translateY(-1px); }

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