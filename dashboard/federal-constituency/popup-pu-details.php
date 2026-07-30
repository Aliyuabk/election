<?php
// ============================================================
// POPUP: POLLING UNIT DETAILS
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

$pu_id = isset($_GET['pu']) ? (int)$_GET['pu'] : 0;
$tenant_id = SessionManager::get('tenant_id');
$db = getDB();

if ($pu_id <= 0) {
    exit('<p style="color:var(--gray-500);">Invalid PU ID</p>');
}

// Get PU details
$pu = null;
try {
    $stmt = $db->prepare("
        SELECT 
            pu.*,
            w.name as ward_name,
            l.name as lga_name,
            (SELECT COUNT(*) FROM agent_assignments aa WHERE aa.pu_id = pu.id AND aa.status = 'active') as agent_count,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.pu_id = pu.id AND r.tenant_id = ?) as result_count,
            (SELECT COUNT(*) FROM incidents i WHERE i.pu_id = pu.id AND i.tenant_id = ?) as incident_count
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        WHERE pu.id = ?
    ");
    $stmt->execute([$tenant_id, $tenant_id, $pu_id]);
    $pu = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching PU: " . $e->getMessage());
    exit('<p style="color:var(--gray-500);">Error loading data</p>');
}

if (!$pu) {
    exit('<p style="color:var(--gray-500);">Polling unit not found</p>');
}
?>
<div class="popup-body-content">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
        <div>
            <h4 style="font-size:1.1rem;font-weight:700;margin:0;">
                <?php echo htmlspecialchars($pu['name']); ?>
            </h4>
            <div style="color:var(--gray-500);font-size:0.85rem;">
                Code: <?php echo htmlspecialchars($pu['code'] ?? 'N/A'); ?>
                <span style="margin-left:10px;"><?php echo htmlspecialchars($pu['ward_name']); ?></span>
                <span style="margin-left:10px;"><?php echo htmlspecialchars($pu['lga_name']); ?></span>
            </div>
        </div>
        <div>
            <span class="status-badge <?php echo ($pu['is_active'] ?? 1) ? 'verified' : 'no-result'; ?>">
                <?php echo ($pu['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?>
            </span>
            <span style="margin-left:8px;" class="status-badge <?php echo ($pu['is_rural'] ?? 0) ? 'verified' : 'no-result'; ?>">
                <?php echo ($pu['is_rural'] ?? 0) ? 'Rural' : 'Urban'; ?>
            </span>
        </div>
    </div>
    
    <div class="detail-row">
        <div class="detail-item">
            <div class="label">Registered Voters</div>
            <div class="value"><?php echo number_format($pu['registered_voters'] ?? 0); ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Accredited Voters</div>
            <div class="value"><?php echo number_format($pu['accredited_voters'] ?? 0); ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Agents Assigned</div>
            <div class="value"><?php echo number_format($pu['agent_count'] ?? 0); ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Results Submitted</div>
            <div class="value"><?php echo number_format($pu['result_count'] ?? 0); ?></div>
        </div>
        <div class="detail-item">
            <div class="label">Incidents Reported</div>
            <div class="value"><?php echo number_format($pu['incident_count'] ?? 0); ?></div>
        </div>
        <?php if (!empty($pu['gps_lat']) && !empty($pu['gps_lng'])): ?>
            <div class="detail-item">
                <div class="label">GPS Location</div>
                <div class="value">
                    <a href="https://maps.google.com/?q=<?php echo $pu['gps_lat']; ?>,<?php echo $pu['gps_lng']; ?>" target="_blank" style="color:var(--primary);text-decoration:none;font-size:0.8rem;">
                        <i class="fas fa-map-marker-alt"></i> View on Map
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;">
        <a href="pu-details.php?id=<?php echo $pu['id']; ?>" class="btn btn-primary" style="padding:8px 18px;border-radius:8px;text-decoration:none;background:var(--primary);color:white;font-weight:500;font-size:0.85rem;">
            <i class="fas fa-eye"></i> Full Details
        </a>
        <a href="verify-ec8a.php?pu=<?php echo $pu['id']; ?>" class="btn btn-secondary" style="padding:8px 18px;border-radius:8px;text-decoration:none;background:var(--gray-100);color:var(--gray-600);font-weight:500;font-size:0.85rem;">
            <i class="fas fa-check-double"></i> Verify Results
        </a>
        <a href="incidents.php?pu=<?php echo $pu['id']; ?>" class="btn btn-secondary" style="padding:8px 18px;border-radius:8px;text-decoration:none;background:var(--gray-100);color:var(--gray-600);font-weight:500;font-size:0.85rem;">
            <i class="fas fa-exclamation-triangle"></i> Incidents
        </a>
    </div>
</div>