<?php
// ============================================================
// POPUP: POLLING UNITS
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

$ward_id = isset($_GET['ward']) ? (int)$_GET['ward'] : 0;
$lga_id = isset($_GET['lga']) ? (int)$_GET['lga'] : 0;
$tenant_id = SessionManager::get('tenant_id');
$db = getDB();

if ($ward_id <= 0 && $lga_id <= 0) {
    exit('<p style="color:var(--gray-500);">Invalid parameters</p>');
}

// Get location name
$location_name = '';
$location_type = '';
try {
    if ($ward_id > 0) {
        $stmt = $db->prepare("SELECT name FROM wards WHERE id = ?");
        $stmt->execute([$ward_id]);
        $location_name = $stmt->fetchColumn() ?: 'Ward';
        $location_type = 'Ward';
    } else {
        $stmt = $db->prepare("SELECT name FROM lgas WHERE id = ?");
        $stmt->execute([$lga_id]);
        $location_name = $stmt->fetchColumn() ?: 'LGA';
        $location_type = 'LGA';
    }
} catch (Exception $e) {
    error_log("Error fetching location name: " . $e->getMessage());
}

// Build query
$where = [];
$params = [];
if ($ward_id > 0) {
    $where[] = "pu.ward_id = ?";
    $params[] = $ward_id;
} elseif ($lga_id > 0) {
    $where[] = "w.lga_id = ?";
    $params[] = $lga_id;
}
$params[] = $tenant_id;
$params[] = $tenant_id;

$where_clause = implode(" AND ", $where);

// Get polling units
$pus = [];
try {
    $stmt = $db->prepare("
        SELECT 
            pu.*,
            w.name as ward_name,
            l.name as lga_name,
            (SELECT COUNT(*) FROM agent_assignments aa WHERE aa.pu_id = pu.id AND aa.status = 'active') as agent_count,
            (SELECT COUNT(*) FROM results_ec8a r WHERE r.pu_id = pu.id AND r.tenant_id = ?) as result_count,
            (SELECT status FROM results_ec8a r WHERE r.pu_id = pu.id AND r.tenant_id = ? ORDER BY r.created_at DESC LIMIT 1) as last_result_status
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        WHERE $where_clause AND pu.is_active = 1
        ORDER BY pu.name ASC
        LIMIT 100
    ");
    $stmt->execute($params);
    $pus = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching PUs: " . $e->getMessage());
}
?>
<div class="popup-body-content">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
        <h4 style="font-size:1rem;font-weight:700;margin:0;">
            <i class="fas fa-flag-checkered" style="color:var(--primary);"></i> 
            Polling Units - <?php echo htmlspecialchars($location_name); ?>
            <span style="font-weight:400;font-size:0.8rem;color:var(--gray-400);margin-left:8px;">
                (<?php echo $location_type; ?>)
            </span>
        </h4>
        <span style="font-size:0.8rem;color:var(--gray-400);">
            <?php echo count($pus); ?> PUs
            <?php if (count($pus) >= 100): ?>
                <span style="font-size:0.7rem;color:var(--gray-400);">(showing first 100)</span>
            <?php endif; ?>
        </span>
    </div>
    
    <?php if (count($pus) > 0): ?>
        <div class="pu-table-wrap">
            <table class="pu-table">
                <thead>
                    <tr>
                        <th>PU Name</th>
                        <th>Code</th>
                        <th>Ward</th>
                        <th>Agents</th>
                        <th>Results</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pus as $pu): 
                        $status = $pu['last_result_status'] ?? 'no-result';
                        $status_label = $status === 'no-result' ? 'No Result' : ucfirst($status);
                        $status_class = $status === 'verified' ? 'verified' : ($status === 'pending' ? 'pending' : 'no-result');
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($pu['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($pu['code'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($pu['ward_name']); ?></td>
                            <td><?php echo number_format($pu['agent_count'] ?? 0); ?></td>
                            <td><?php echo number_format($pu['result_count'] ?? 0); ?></td>
                            <td>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $status_label; ?>
                                </span>
                            </td>
                            <td>
                                <a href="#" onclick="openPopup('pu-details&pu=<?php echo $pu['id']; ?>')" style="color:var(--primary);text-decoration:none;font-size:0.8rem;">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="text-align:center;padding:20px;color:var(--gray-500);">
            <i class="fas fa-inbox" style="display:block;font-size:1.5rem;color:var(--gray-300);margin-bottom:8px;"></i>
            No polling units found.
        </div>
    <?php endif; ?>
</div>