<?php
// ============================================================
// STATE COORDINATOR - LGA DASHBOARD VIEW
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

// Get LGA ID from URL
$lga_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($lga_id <= 0) {
    header('Location: monitor-lgas.php?error=invalid_lga');
    exit();
}

$db = getDB();

// ============================================================
// FETCH LGA DATA
// ============================================================
$lga_data = null;
$state_name = '';

try {
    $stmt = $db->prepare("
        SELECT 
            l.*,
            s.name as state_name
        FROM lgas l
        JOIN states s ON l.state_id = s.id
        WHERE l.id = ? AND l.state_id = ?
    ");
    $stmt->execute([$lga_id, $state_id]);
    $lga_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lga_data) {
        header('Location: monitor-lgas.php?error=lga_not_found');
        exit();
    }
    
    $state_name = $lga_data['state_name'];
    
} catch (Exception $e) {
    error_log("LGA Dashboard Error: " . $e->getMessage());
    header('Location: monitor-lgas.php?error=database_error');
    exit();
}

// ============================================================
// FETCH LGA STATISTICS
// ============================================================
$stats = [
    'wards' => 0,
    'pus' => 0,
    'coordinators' => 0,
    'ward_coordinators' => 0,
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
    'progress' => 0
];

try {
    // Wards
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM wards WHERE lga_id = ? AND is_active = 1");
    $stmt->execute([$lga_id]);
    $stats['wards'] = $stmt->fetchColumn() ?: 0;
    
    // PUs
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM polling_units pu JOIN wards w ON pu.ward_id = w.id WHERE w.lga_id = ? AND pu.is_active = 1");
    $stmt->execute([$lga_id]);
    $stats['pus'] = $stmt->fetchColumn() ?: 0;
    
    // LGA Coordinators
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'lga' AND u.jurisdiction_id = ? AND u.status = 'active'");
    $stmt->execute([$tenant_id, $lga_id]);
    $stats['coordinators'] = $stmt->fetchColumn() ?: 0;
    
    // Ward Coordinators
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'ward' AND u.jurisdiction_id IN (SELECT id FROM wards WHERE lga_id = ?) AND u.status = 'active'");
    $stmt->execute([$tenant_id, $lga_id]);
    $stats['ward_coordinators'] = $stmt->fetchColumn() ?: 0;
    
    // PU Agents
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'pu_agent' AND u.jurisdiction_id IN (SELECT id FROM polling_units WHERE ward_id IN (SELECT id FROM wards WHERE lga_id = ?)) AND u.status = 'active'");
    $stmt->execute([$tenant_id, $lga_id]);
    $stats['agents'] = $stmt->fetchColumn() ?: 0;
    
    // Results
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged
        FROM results_ec8a 
        WHERE tenant_id = ? AND lga_id = ?
    ");
    $stmt->execute([$tenant_id, $lga_id]);
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
        WHERE tenant_id = ? AND lga_id = ?
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['incidents'] = $result['total'] ?? 0;
    $stats['open_incidents'] = $result['open'] ?? 0;
    $stats['critical_incidents'] = $result['critical'] ?? 0;
    
    // Elections
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL 
        AND JSON_CONTAINS(lgas_json, JSON_QUOTE(?))
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $stats['elections'] = $stmt->fetchColumn() ?: 0;
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL AND status = 'active'
        AND JSON_CONTAINS(lgas_json, JSON_QUOTE(?))
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $stats['active_elections'] = $stmt->fetchColumn() ?: 0;
    
    // Progress
    $stats['progress'] = $stats['pus'] > 0 ? round(($stats['verified_results'] / $stats['pus']) * 100) : 0;
    
} catch (Exception $e) {
    error_log("LGA Stats Error: " . $e->getMessage());
}

// ============================================================
// FETCH WARDS WITH PROGRESS
// ============================================================
$wards = [];
try {
    $stmt = $db->prepare("
        SELECT 
            w.id,
            w.name,
            w.code,
            w.registered_voters,
            (SELECT COUNT(*) FROM polling_units WHERE ward_id = w.id AND is_active = 1) as pu_count,
            (SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'ward' AND u.jurisdiction_id = w.id AND u.status = 'active') as coordinators,
            (SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'pu_agent' AND u.jurisdiction_id IN (SELECT id FROM polling_units WHERE ward_id = w.id) AND u.status = 'active') as agents,
            (SELECT COUNT(*) FROM results_ec8a r2 WHERE r2.tenant_id = ? AND r2.ward_id = w.id) as total_results,
            (SELECT COUNT(*) FROM results_ec8a r2 WHERE r2.tenant_id = ? AND r2.ward_id = w.id AND r2.status = 'verified') as verified_results,
            (SELECT COUNT(*) FROM incidents i WHERE i.tenant_id = ? AND i.ward_id = w.id) as incidents
        FROM wards w
        WHERE w.lga_id = ? AND w.is_active = 1
        ORDER BY w.name ASC
    ");
    $stmt->execute([$tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $lga_id]);
    $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $wards = [];
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'LGA Dashboard';
$page_subtitle = $lga_data['name'] ?? 'LGA';
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
                <span style="font-weight:600;color:var(--gray-800);"><?php echo htmlspecialchars($lga_data['name'] ?? 'LGA'); ?></span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;display:flex;align-items:center;gap:12px;">
                        <span><?php echo htmlspecialchars($lga_data['name'] ?? 'LGA'); ?></span>
                        <span style="font-size:0.7rem;background:<?php echo ($lga_data['is_active'] ?? 0) ? 'var(--primary)' : '#6B7280'; ?>;color:white;padding:2px 12px;border-radius:20px;font-weight:500;">
                            <?php echo ($lga_data['is_active'] ?? 0) ? 'Active' : 'Inactive'; ?>
                        </span>
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-flag"></i> <?php echo htmlspecialchars($state_name); ?>
                        <?php if ($lga_data['registered_voters'] > 0): ?>
                            • <i class="fas fa-users"></i> <?php echo number_format($lga_data['registered_voters']); ?> registered voters
                        <?php endif; ?>
                        <?php if (!empty($lga_data['code'])): ?>
                            • <i class="fas fa-code"></i> Code: <?php echo htmlspecialchars($lga_data['code']); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="monitor-lgas.php" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <a href="lga-coordinators.php?id=<?php echo $lga_id; ?>" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-user-tie"></i> Coordinators
                    </a>
                    <a href="lga-results.php?id=<?php echo $lga_id; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
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
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-layer-group"></i></div>
                <div class="stat-number"><?php echo number_format($stats['wards']); ?></div>
                <div class="stat-label">Wards</div>
                <div class="stat-change"><i class="fas fa-user-tie"></i> <?php echo $stats['ward_coordinators']; ?> coordinators</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($stats['pus']); ?></div>
                <div class="stat-label">Polling Units</div>
                <div class="stat-change"><i class="fas fa-users"></i> <?php echo $stats['agents']; ?> agents</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format($stats['coordinators']); ?></div>
                <div class="stat-label">LGA Coordinators</div>
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
                <div class="stat-number"><?php echo number_format($stats['total_results']); ?></div>
                <div class="stat-label">Total Results</div>
                <div class="stat-change"><i class="fas fa-upload"></i> Submitted</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['incidents']); ?></div>
                <div class="stat-label">Incidents</div>
                <div class="stat-change down"><i class="fas fa-clock"></i> <?php echo $stats['open_incidents']; ?> open</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pink"><i class="fas fa-clipboard-list"></i></div>
                <div class="stat-number"><?php echo number_format($stats['wards']); ?></div>
                <div class="stat-label">Active Wards</div>
                <div class="stat-change"><i class="fas fa-check-circle"></i> Full coverage</div>
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

        <!-- Wards Table -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0;">
                    <i class="fas fa-layer-group" style="color:var(--primary);margin-right:6px;"></i>
                    Wards in <?php echo htmlspecialchars($lga_data['name'] ?? 'LGA'); ?>
                </h4>
                <span style="font-size:0.75rem;color:var(--gray-500);"><?php echo count($wards); ?> wards</span>
            </div>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                    <thead style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
                        <tr>
                            <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--gray-600);">Ward</th>
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
                        <?php if (count($wards) > 0): ?>
                            <?php foreach ($wards as $ward): 
                                $pu_count = $ward['pu_count'] ?? 0;
                                $verified_results = $ward['verified_results'] ?? 0;
                                $ward_progress = $pu_count > 0 ? min(100, round(($verified_results / $pu_count) * 100)) : 0;
                                $progress_color = $ward_progress >= 80 ? '#10B981' : ($ward_progress >= 50 ? '#F59E0B' : '#EF4444');
                            ?>
                                <tr style="border-bottom:1px solid var(--gray-100);transition:var(--transition);hover:background:var(--gray-50);">
                                    <td style="padding:10px 14px;">
                                        <div style="font-weight:500;"><?php echo htmlspecialchars($ward['name']); ?></div>
                                        <?php if (!empty($ward['code'])): ?>
                                            <div style="font-size:0.65rem;color:var(--gray-400);">Code: <?php echo htmlspecialchars($ward['code']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;font-weight:600;"><?php echo number_format($pu_count); ?></td>
                                    <td style="padding:10px 14px;text-align:center;color:var(--primary);"><?php echo number_format($ward['coordinators'] ?? 0); ?></td>
                                    <td style="padding:10px 14px;text-align:center;color:var(--secondary);"><?php echo number_format($ward['agents'] ?? 0); ?></td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <span style="font-weight:600;"><?php echo number_format($verified_results); ?></span>
                                        <span style="font-size:0.6rem;color:var(--gray-400);">/ <?php echo number_format($ward['total_results'] ?? 0); ?></span>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <div style="display:flex;align-items:center;gap:6px;justify-content:center;">
                                            <div style="width:50px;height:4px;background:var(--gray-200);border-radius:4px;overflow:hidden;">
                                                <div style="width:<?php echo $ward_progress; ?>%;height:100%;background:<?php echo $progress_color; ?>;border-radius:4px;"></div>
                                            </div>
                                            <span style="font-size:0.6rem;font-weight:600;color:<?php echo $progress_color; ?>;"><?php echo $ward_progress; ?>%</span>
                                        </div>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <?php if (($ward['incidents'] ?? 0) > 0): ?>
                                            <span style="color:var(--danger);font-weight:600;"><?php echo number_format($ward['incidents'] ?? 0); ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);font-size:0.7rem;">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 14px;text-align:center;">
                                        <a href="ward-dashboard.php?id=<?php echo $ward['id']; ?>" class="btn-sm" style="padding:4px 10px;border-radius:6px;background:var(--primary);color:white;text-decoration:none;font-size:0.65rem;transition:var(--transition);">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="padding:30px;text-align:center;color:var(--gray-500);">
                                    <i class="fas fa-layer-group" style="font-size:1.5rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                                    No wards found in this LGA
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Actions -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
            <a href="lga-coordinators.php?id=<?php echo $lga_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-user-tie" style="color:var(--secondary);"></i>
                <span>Manage Coordinators</span>
            </a>
            <a href="lga-results.php?id=<?php echo $lga_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-file-alt" style="color:var(--warning);"></i>
                <span>View All Results</span>
            </a>
            <a href="broadcasts-create.php?lga=<?php echo $lga_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-bullhorn" style="color:var(--primary);"></i>
                <span>Broadcast to LGA</span>
            </a>
            <a href="reports.php?type=lga&id=<?php echo $lga_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-file-pdf" style="color:var(--danger);"></i>
                <span>Generate Report</span>
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

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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