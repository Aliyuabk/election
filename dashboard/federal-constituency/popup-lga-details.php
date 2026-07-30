<?php
// ============================================================
// POPUP: LGA DETAILS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    exit('Unauthorized');
}

if (SessionManager::get('role_level') !== 'federal_constituency') {
    exit('Unauthorized');
}

$lga_id = isset($_GET['lga']) ? (int)$_GET['lga'] : 0;
$tenant_id = SessionManager::get('tenant_id');
$db = getDB();

if ($lga_id <= 0) {
    exit('<p style="color:var(--gray-500);">Invalid LGA ID</p>');
}

// Get LGA details
$lga = null;
try {
    $stmt = $db->prepare("
        SELECT 
            l.*,
            s.name as state_name,
            COUNT(DISTINCT w.id) as ward_count,
            COUNT(DISTINCT pu.id) as pu_count,
            (SELECT COUNT(*) FROM users u 
             JOIN roles r ON u.role_id = r.id 
             WHERE u.lga_id = l.id AND u.tenant_id = ? AND u.status = 'active' AND r.level = 'lga') as coordinator_count,
            (SELECT COUNT(*) FROM results_ec8a r 
             JOIN polling_units pu2 ON r.pu_id = pu2.id 
             JOIN wards w2 ON pu2.ward_id = w2.id 
             WHERE w2.lga_id = l.id AND r.tenant_id = ?) as result_count,
            (SELECT COUNT(*) FROM incidents i WHERE i.lga_id = l.id AND i.tenant_id = ?) as incident_count
        FROM lgas l
        JOIN states s ON l.state_id = s.id
        LEFT JOIN wards w ON w.lga_id = l.id AND w.is_active = 1
        LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
        WHERE l.id = ?
        GROUP BY l.id
    ");
    $stmt->execute([$tenant_id, $tenant_id, $tenant_id, $lga_id]);
    $lga = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching LGA: " . $e->getMessage());
    exit('<p style="color:var(--gray-500);">Error loading data</p>');
}

if (!$lga) {
    exit('<p style="color:var(--gray-500);">LGA not found</p>');
}
?>
<div class="popup-body-content">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
        <div>
            <h4 style="font-size:1.1rem;font-weight:700;margin:0;">
                <?php echo htmlspecialchars($lga['name']); ?>
            </h4>
            <div style="color:var(--gray-500);font-size:0.85rem;">
                <?php echo htmlspecialchars($lga['code'] ?? 'N/A'); ?>
                <span style="margin-left:10px;"><?php echo htmlspecialchars($lga['state_name']); ?></span>
            </div>
        </div>
        <div style="text-align:right;">
            <span class="status-badge <?php echo ($lga['is_active'] ?? 1) ? 'verified' : 'no-result'; ?>">
                <?php echo ($lga['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?>
            </span>
        </div>
    </div>
    
    <div class="detail-row">
        <div class="detail-item">
            <div class="label">Total Wards</div>
            <div class="value"><?php echo number_format($lga['ward_count'] ?? 0); ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Total Polling Units</div>
            <div class="value"><?php echo number_format($lga['pu_count'] ?? 0); ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Registered Voters</div>
            <div class="value"><?php echo number_format($lga['registered_voters'] ?? 0); ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Coordinators</div>
            <div class="value"><?php echo number_format($lga['coordinator_count'] ?? 0); ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Results Submitted</div>
            <div class="value"><?php echo number_format($lga['result_count'] ?? 0); ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Incidents</div>
            <div class="value"><?php echo number_format($lga['incident_count'] ?? 0); ?></div>
        </div>
    </div>
    
    <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;">
        <a href="#" class="btn btn-primary" onclick="openPopup('wards&lga=<?php echo $lga['id']; ?>')" style="padding:8px 18px;border-radius:8px;text-decoration:none;background:var(--primary);color:white;font-weight:500;font-size:0.85rem;">
            <i class="fas fa-layer-group"></i> View Wards
        </a>
        <a href="#" class="btn btn-primary" onclick="openPopup('pus&lga=<?php echo $lga['id']; ?>')" style="padding:8px 18px;border-radius:8px;text-decoration:none;background:var(--primary);color:white;font-weight:500;font-size:0.85rem;">
            <i class="fas fa-flag-checkered"></i> View PUs
        </a>
        <a href="coordinators.php?lga=<?php echo $lga['id']; ?>" class="btn btn-secondary" style="padding:8px 18px;border-radius:8px;text-decoration:none;background:var(--gray-100);color:var(--gray-600);font-weight:500;font-size:0.85rem;">
            <i class="fas fa-user-tie"></i> Coordinators
        </a>
        <a href="incidents.php?lga=<?php echo $lga['id']; ?>" class="btn btn-secondary" style="padding:8px 18px;border-radius:8px;text-decoration:none;background:var(--gray-100);color:var(--gray-600);font-weight:500;font-size:0.85rem;">
            <i class="fas fa-exclamation-triangle"></i> Incidents
        </a>
    </div>
</div>