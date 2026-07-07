<?php
// ============================================================
// STATE COORDINATOR - WARD DASHBOARD VIEW
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

// Get Ward ID from URL
$ward_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($ward_id <= 0) {
    header('Location: monitor-lgas.php?error=invalid_ward');
    exit();
}

$db = getDB();

// ============================================================
// FETCH WARD DATA WITH LOCATION INFO
// ============================================================
$ward_data = null;
$lga_name = '';
$state_name = '';
$lga_id = 0;

try {
    $stmt = $db->prepare("
        SELECT 
            w.*,
            l.name as lga_name,
            l.id as lga_id,
            s.name as state_name
        FROM wards w
        JOIN lgas l ON w.lga_id = l.id
        JOIN states s ON l.state_id = s.id
        WHERE w.id = ? AND l.state_id = ?
    ");
    $stmt->execute([$ward_id, $state_id]);
    $ward_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ward_data) {
        header('Location: monitor-lgas.php?error=ward_not_found');
        exit();
    }
    
    $lga_name = $ward_data['lga_name'];
    $state_name = $ward_data['state_name'];
    $lga_id = $ward_data['lga_id'];
    
} catch (Exception $e) {
    error_log("Ward Dashboard Error: " . $e->getMessage());
    header('Location: monitor-lgas.php?error=database_error');
    exit();
}

// ============================================================
// FETCH WARD STATISTICS
// ============================================================
$stats = [
    'pus' => 0,
    'coordinators' => 0,
    'agents' => 0,
    'total_results' => 0,
    'verified_results' => 0,
    'pending_results' => 0,
    'flagged_results' => 0,
    'incidents' => 0,
    'open_incidents' => 0,
    'critical_incidents' => 0,
    'elections' => 0,
    'active_elections' => 0,
    'progress' => 0,
    'ec8b_count' => 0
];

try {
    // Polling Units
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM polling_units WHERE ward_id = ? AND is_active = 1");
    $stmt->execute([$ward_id]);
    $stats['pus'] = $stmt->fetchColumn() ?: 0;
    
    // Ward Coordinators
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.tenant_id = ? AND r.level = 'ward' 
        AND u.jurisdiction_id = ? AND u.status = 'active'
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $stats['coordinators'] = $stmt->fetchColumn() ?: 0;
    
    // PU Agents
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.tenant_id = ? AND r.level = 'pu_agent' 
        AND u.jurisdiction_id IN (SELECT id FROM polling_units WHERE ward_id = ?) 
        AND u.status = 'active'
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $stats['agents'] = $stmt->fetchColumn() ?: 0;
    
    // Results
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged
        FROM results_ec8a 
        WHERE tenant_id = ? AND ward_id = ?
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_results'] = $result['total'] ?? 0;
    $stats['verified_results'] = $result['verified'] ?? 0;
    $stats['pending_results'] = $result['pending'] ?? 0;
    $stats['flagged_results'] = $result['flagged'] ?? 0;
    
    // Incidents
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('reported', 'investigating') THEN 1 ELSE 0 END) as open,
            SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical
        FROM incidents 
        WHERE tenant_id = ? AND ward_id = ?
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['incidents'] = $result['total'] ?? 0;
    $stats['open_incidents'] = $result['open'] ?? 0;
    $stats['critical_incidents'] = $result['critical'] ?? 0;
    
    // EC8B
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM results_ec8b WHERE tenant_id = ? AND ward_id = ?");
    $stmt->execute([$tenant_id, $ward_id]);
    $stats['ec8b_count'] = $stmt->fetchColumn() ?: 0;
    
    // Elections
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL 
        AND JSON_CONTAINS(wards_json, JSON_QUOTE(?))
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $stats['elections'] = $stmt->fetchColumn() ?: 0;
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL AND status = 'active'
        AND JSON_CONTAINS(wards_json, JSON_QUOTE(?))
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $stats['active_elections'] = $stmt->fetchColumn() ?: 0;
    
    // Progress
    $stats['progress'] = $stats['pus'] > 0 ? round(($stats['verified_results'] / $stats['pus']) * 100) : 0;
    
} catch (Exception $e) {
    error_log("Ward Stats Error: " . $e->getMessage());
}

// ============================================================
// FETCH POLLING UNITS WITH PROGRESS
// ============================================================
$polling_units = [];
try {
    $stmt = $db->prepare("
        SELECT 
            pu.id,
            pu.name,
            pu.code,
            pu.registered_voters,
            pu.accredited_voters,
            pu.network_quality,
            pu.is_rural,
            (SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'pu_agent' AND u.jurisdiction_id = pu.id AND u.status = 'active') as agents,
            (SELECT COUNT(*) FROM results_ec8a r2 WHERE r2.tenant_id = ? AND r2.pu_id = pu.id) as total_results,
            (SELECT COUNT(*) FROM results_ec8a r2 WHERE r2.tenant_id = ? AND r2.pu_id = pu.id AND r2.status = 'verified') as verified_results,
            (SELECT COUNT(*) FROM incidents i WHERE i.tenant_id = ? AND i.pu_id = pu.id) as incidents
        FROM polling_units pu
        WHERE pu.ward_id = ? AND pu.is_active = 1
        ORDER BY pu.name ASC
    ");
    $stmt->execute([$tenant_id, $tenant_id, $tenant_id, $tenant_id, $ward_id]);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $polling_units = [];
}

// ============================================================
// FETCH RECENT ACTIVITIES
// ============================================================
$recent_activities = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, u.full_name as user_name
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.tenant_id = ? 
        AND (a.entity_type = 'ward' AND a.entity_id = ?)
        OR (a.entity_type = 'pu' AND a.entity_id IN (SELECT id FROM polling_units WHERE ward_id = ?))
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$tenant_id, $ward_id, $ward_id]);
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_activities = [];
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Ward Dashboard';
$page_subtitle = $ward_data['name'] ?? 'Ward';
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
                <a href="lga-dashboard.php?id=<?php echo $lga_id; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($lga_name); ?></a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);"><?php echo htmlspecialchars($ward_data['name'] ?? 'Ward'); ?></span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <?php echo htmlspecialchars($ward_data['name'] ?? 'Ward'); ?>
                        <span style="font-size:0.7rem;background:<?php echo ($ward_data['is_active'] ?? 0) ? 'var(--primary)' : '#6B7280'; ?>;color:white;padding:2px 12px;border-radius:20px;font-weight:500;margin-left:8px;">
                            <?php echo ($ward_data['is_active'] ?? 0) ? 'Active' : 'Inactive'; ?>
                        </span>
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($lga_name); ?> LGA, <?php echo htmlspecialchars($state_name); ?>
                        <?php if (!empty($ward_data['code'])): ?>
                            • <i class="fas fa-code"></i> Code: <?php echo htmlspecialchars($ward_data['code']); ?>
                        <?php endif; ?>
                        <?php if (($ward_data['registered_voters'] ?? 0) > 0): ?>
                            • <i class="fas fa-users"></i> <?php echo number_format($ward_data['registered_voters'] ?? 0); ?> registered voters
                        <?php endif; ?>
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="lga-dashboard.php?id=<?php echo $lga_id; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <a href="ward-edit.php?id=<?php echo $ward_id; ?>" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-edit"></i> Edit Ward
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
                        <?php echo number_format($stats['verified_results']); ?> of <?php echo number_format($stats['pus']); ?> polling units verified
                    </p>
                </div>
                <span style="font-size:1.2rem;font-weight:700;color:<?php echo $stats['progress'] >= 80 ? '#10B981' : ($stats['progress'] >= 50 ? '#F59E0B' : '#EF4444'); ?>;">
                    <?php echo $stats['progress']; ?>%
                </span>
            </div>
            <div style="width:100%;height:12px;background:var(--gray-100);border-radius:8px;overflow:hidden;">
                <div style="width:<?php echo $stats['progress']; ?>%;height:100%;background:linear-gradient(90deg, <?php echo $stats['progress'] >= 80 ? '#10B981' : ($stats['progress'] >= 50 ? '#F59E0B' : '#EF4444'); ?>, <?php echo $stats['progress'] >= 80 ? '#34D399' : ($stats['progress'] >= 50 ? '#FBBF24' : '#F87171'); ?>);border-radius:8px;transition:width 1s ease;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:0.65rem;color:var(--gray-400);">
                <span>0%</span>
                <span>50%</span>
                <span>100%</span>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($stats['pus']); ?></div>
                <div class="stat-label">Polling Units</div>
                <div class="stat-change"><i class="fas fa-users"></i> <?php echo $stats['agents']; ?> agents</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format($stats['coordinators']); ?></div>
                <div class="stat-label">Ward Coordinators</div>
                <div class="stat-change"><i class="fas fa-users"></i> Active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-vote-yea"></i></div>
                <div class="stat-number"><?php echo number_format($stats['elections']); ?></div>
                <div class="stat-label">Elections</div>
                <div class="stat-change up"><i class="fas fa-play"></i> <?php echo $stats['active_elections']; ?> active</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-check-double"></i></div>
                <div class="stat-number"><?php echo number_format($stats['verified_results']); ?></div>
                <div class="stat-label">Verified Results</div>
                <div class="stat-change down"><i class="fas fa-clock"></i> <?php echo $stats['pending_results']; ?> pending</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number"><?php echo number_format($stats['ec8b_count']); ?></div>
                <div class="stat-label">EC8B Forms</div>
                <div class="stat-change"><i class="fas fa-upload"></i> Submitted</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['incidents']); ?></div>
                <div class="stat-label">Incidents</div>
                <div class="stat-change down"><i class="fas fa-clock"></i> <?php echo $stats['open_incidents']; ?> open</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($ward_data['registered_voters'] ?? 0); ?></div>
                <div class="stat-label">Registered Voters</div>
                <div class="stat-change"><i class="fas fa-address-card"></i> Total</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pink"><i class="fas fa-flag"></i></div>
                <div class="stat-number"><?php echo number_format($stats['pus']); ?></div>
                <div class="stat-label">Active PUs</div>
                <div class="stat-change"><i class="fas fa-check-circle"></i> All active</div>
            </div>
        </div>

        <!-- Incident Summary -->
        <?php if ($stats['incidents'] > 0): ?>
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
                        <span class="badge badge-danger"><?php echo $stats['critical_incidents']; ?></span>
                        <span style="color:var(--gray-500);">Critical</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;font-size:0.8rem;">
                        <span class="badge badge-warning"><?php echo $stats['open_incidents']; ?></span>
                        <span style="color:var(--gray-500);">Open</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Polling Units Table -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0;">
                    <i class="fas fa-flag-checkered" style="color:var(--primary);margin-right:6px;"></i>
                    Polling Units in <?php echo htmlspecialchars($ward_data['name'] ?? 'Ward'); ?>
                </h4>
                <span style="font-size:0.75rem;color:var(--gray-500);"><?php echo count($polling_units); ?> polling units</span>
            </div>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                    <thead style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
                        <tr>
                            <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--gray-600);">Polling Unit</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Code</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Agents</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Voters</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Results</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Progress</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Network</th>
                            <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-600);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($polling_units) > 0): ?>
                            <?php foreach ($polling_units as $pu): 
                                $pu_total = $pu['total_results'] ?? 0;
                                $pu_verified = $pu['verified_results'] ?? 0;
                                $pu_progress = $pu_total > 0 ? min(100, round(($pu_verified / $pu_total) * 100)) : 0;
                                $progress_color = $pu_progress >= 80 ? '#10B981' : ($pu_progress >= 50 ? '#F59E0B' : '#EF4444');
                                
                                $network_colors = [
                                    '5g' => '#10B981',
                                    '4g' => '#3B82F6',
                                    '3g' => '#F59E0B',
                                    '2g' => '#EF4444',
                                    'none' => '#6B7280'
                                ];
                                $network_color = $network_colors[$pu['network_quality']] ?? '#6B7280';
                            ?>
                                <tr style="border-bottom:1px solid var(--gray-100);transition:var(--transition);hover:background:var(--gray-50);">
                                    <td style="padding:10px 14px;">
                                        <div style="font-weight:500;"><?php echo htmlspecialchars($pu['name']); ?></div>
                                        <?php if ($pu['is_rural']): ?>
                                            <span style="font-size:0.6rem;background:#FEF3C7;color:#92400E;padding:1px 8px;border-radius:10px;">Rural</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-size:0.75rem;color:var(--gray-500);">
                                        <?php echo htmlspecialchars($pu['code'] ?? 'N/A'); ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;color:var(--secondary);font-weight:600;">
                                        <?php echo number_format($pu['agents'] ?? 0); ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <?php echo number_format($pu['registered_voters'] ?? 0); ?>
                                        <?php if (($pu['accredited_voters'] ?? 0) > 0): ?>
                                            <div style="font-size:0.6rem;color:var(--gray-400);">
                                                Accredited: <?php echo number_format($pu['accredited_voters']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <span style="font-weight:600;"><?php echo number_format($pu_verified); ?></span>
                                        <span style="font-size:0.6rem;color:var(--gray-400);">/ <?php echo number_format($pu_total); ?></span>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <div style="display:flex;align-items:center;gap:6px;justify-content:center;">
                                            <div style="width:50px;height:4px;background:var(--gray-200);border-radius:4px;overflow:hidden;">
                                                <div style="width:<?php echo $pu_progress; ?>%;height:100%;background:<?php echo $progress_color; ?>;border-radius:4px;"></div>
                                            </div>
                                            <span style="font-size:0.6rem;font-weight:600;color:<?php echo $progress_color; ?>;"><?php echo $pu_progress; ?>%</span>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <?php if (!empty($pu['network_quality'])): ?>
                                            <span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:0.6rem;font-weight:600;background:<?php echo $network_color; ?>20;color:<?php echo $network_color; ?>;">
                                                <?php echo strtoupper($pu['network_quality']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);font-size:0.6rem;">Unknown</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <a href="pu-dashboard.php?id=<?php echo $pu['id']; ?>" class="btn-sm" style="padding:4px 10px;border-radius:6px;background:var(--primary);color:white;text-decoration:none;font-size:0.65rem;transition:var(--transition);">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="padding:30px;text-align:center;color:var(--gray-500);">
                                    <i class="fas fa-flag-checkered" style="font-size:1.5rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                                    No polling units found in this ward
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Activities -->
        <div style="background:white;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h4 style="font-size:0.85rem;font-weight:600;margin:0;">
                    <i class="fas fa-clock" style="color:var(--primary);margin-right:6px;"></i>
                    Recent Activities
                </h4>
                <a href="activity-logs.php?ward=<?php echo $ward_id; ?>" style="font-size:0.7rem;color:var(--primary);text-decoration:none;">View All →</a>
            </div>
            <?php if (count($recent_activities) > 0): ?>
                <div style="max-height:300px;overflow-y:auto;">
                    <?php foreach (array_slice($recent_activities, 0, 8) as $activity): ?>
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
                <p style="color:var(--gray-500);text-align:center;padding:16px 0;">No recent activities</p>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
            <a href="polling-units.php?ward=<?php echo $ward_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-flag-checkered" style="color:var(--primary);"></i>
                <span>Manage Polling Units</span>
            </a>
            <a href="broadcasts-create.php?ward=<?php echo $ward_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-bullhorn" style="color:var(--warning);"></i>
                <span>Broadcast to Ward</span>
            </a>
            <a href="reports.php?type=ward&id=<?php echo $ward_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-file-alt" style="color:var(--danger);"></i>
                <span>Generate Report</span>
            </a>
            <a href="pu-agents.php?ward=<?php echo $ward_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-user-tie" style="color:var(--secondary);"></i>
                <span>View PU Agents</span>
            </a>
            <a href="upload-ec8b.php?ward=<?php echo $ward_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-upload" style="color:var(--primary);"></i>
                <span>Upload EC8B</span>
            </a>
            <a href="incidents.php?ward=<?php echo $ward_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-exclamation-triangle" style="color:var(--danger);"></i>
                <span>View Incidents</span>
            </a>
        </div>
    </div>
</main>

<style>
.badge-success { background: #D1FAE5; color: #065F46; padding: 2px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; }
.badge-danger { background: #FEE2E2; color: #991B1B; padding: 2px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; }
.badge-warning { background: #FEF3C7; color: #92400E; padding: 2px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; }

.stat-icon.teal { background: #CCFBF1; color: #0D9488; }
.stat-icon.pink { background: #FCE7F3; color: #DB2777; }

.btn-sm:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.quick-action-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
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