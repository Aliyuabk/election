<?php
// ============================================================
// POPUP: WARDS
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

// Get LGA name
$lga_name = '';
try {
    $stmt = $db->prepare("SELECT name FROM lgas WHERE id = ?");
    $stmt->execute([$lga_id]);
    $lga_name = $stmt->fetchColumn() ?: 'LGA';
} catch (Exception $e) {
    error_log("Error fetching LGA name: " . $e->getMessage());
}

// Get wards
$wards = [];
try {
    $stmt = $db->prepare("
        SELECT 
            w.*,
            l.name as lga_name,
            COUNT(DISTINCT pu.id) as pu_count,
            (SELECT COUNT(*) FROM users u 
             WHERE u.ward_id = w.id AND u.tenant_id = ? AND u.status = 'active') as agent_count
        FROM wards w
        JOIN lgas l ON w.lga_id = l.id
        LEFT JOIN polling_units pu ON pu.ward_id = w.id AND pu.is_active = 1
        WHERE w.lga_id = ? AND w.is_active = 1
        GROUP BY w.id
        ORDER BY w.name ASC
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $wards = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching wards: " . $e->getMessage());
}
?>
<div class="popup-body-content">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
        <h4 style="font-size:1rem;font-weight:700;margin:0;">
            <i class="fas fa-layer-group" style="color:var(--primary);"></i> 
            Wards - <?php echo htmlspecialchars($lga_name); ?>
        </h4>
        <span style="font-size:0.8rem;color:var(--gray-400);"><?php echo count($wards); ?> wards</span>
    </div>
    
    <?php if (count($wards) > 0): ?>
        <div class="ward-grid">
            <?php foreach ($wards as $ward): ?>
                <div class="ward-card">
                    <div class="ward-name"><?php echo htmlspecialchars($ward['name']); ?></div>
                    <div style="font-size:0.7rem;color:var(--gray-400);">
                        <?php echo htmlspecialchars($ward['code'] ?? 'N/A'); ?>
                    </div>
                    <div class="ward-stats">
                        <div class="stat">
                            <div class="number"><?php echo number_format($ward['pu_count'] ?? 0); ?></div>
                            <div class="label">PUs</div>
                        </div>
                        <div class="stat">
                            <div class="number"><?php echo number_format($ward['agent_count'] ?? 0); ?></div>
                            <div class="label">Agents</div>
                        </div>
                    </div>
                    <div style="margin-top:8px;display:flex;gap:6px;">
                        <a href="#" onclick="openPopup('ward-details&ward=<?php echo $ward['id']; ?>')" style="font-size:0.65rem;padding:2px 10px;border-radius:4px;background:var(--primary);color:white;text-decoration:none;">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <a href="#" onclick="openPopup('pus&ward=<?php echo $ward['id']; ?>')" style="font-size:0.65rem;padding:2px 10px;border-radius:4px;background:var(--gray-100);color:var(--gray-600);text-decoration:none;">
                            <i class="fas fa-flag-checkered"></i> PUs
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align:center;padding:20px;color:var(--gray-500);">
            <i class="fas fa-inbox" style="display:block;font-size:1.5rem;color:var(--gray-300);margin-bottom:8px;"></i>
            No wards found.
        </div>
    <?php endif; ?>
</div>