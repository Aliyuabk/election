<?php
// ============================================================
// NATIONAL COORDINATOR - COMPARE STATES
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

$db = getDB();

// ============================================================
// GET SELECTED STATES FOR COMPARISON
// ============================================================
$selected_states = isset($_GET['states']) ? $_GET['states'] : [];
if (is_string($selected_states)) {
    $selected_states = array_filter(explode(',', $selected_states));
}
$selected_states = array_map('intval', $selected_states);

// ============================================================
// FETCH ALL STATES FOR SELECTION
// ============================================================
$all_states = [];
try {
    $stmt = $db->prepare("SELECT id, name, capital FROM states WHERE is_active = 1 ORDER BY name ASC");
    $stmt->execute();
    $all_states = $stmt->fetchAll();
} catch (Exception $e) {
    $all_states = [];
}

// ============================================================
// FETCH COMPARISON DATA
// ============================================================
$comparison_data = [];
$regions = [
    'North Central' => ['Benue', 'FCT', 'Kogi', 'Kwara', 'Nasarawa', 'Niger', 'Plateau'],
    'North East' => ['Adamawa', 'Bauchi', 'Borno', 'Gombe', 'Taraba', 'Yobe'],
    'North West' => ['Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Sokoto', 'Zamfara'],
    'South East' => ['Abia', 'Anambra', 'Ebonyi', 'Enugu', 'Imo'],
    'South South' => ['Akwa Ibom', 'Bayelsa', 'Cross River', 'Delta', 'Edo', 'Rivers'],
    'South West' => ['Ekiti', 'Lagos', 'Ogun', 'Ondo', 'Osun', 'Oyo']
];

if (!empty($selected_states)) {
    $placeholders = implode(',', array_fill(0, count($selected_states), '?'));
    try {
        $stmt = $db->prepare("
            SELECT 
                s.id,
                s.name,
                s.capital,
                s.registered_voters as total_voters,
                (SELECT COUNT(*) FROM lgas WHERE state_id = s.id AND is_active = 1) as total_lgas,
                (SELECT COUNT(*) FROM wards w JOIN lgas l ON w.lga_id = l.id WHERE l.state_id = s.id AND w.is_active = 1) as total_wards,
                (SELECT COUNT(*) FROM polling_units pu JOIN wards w ON pu.ward_id = w.id JOIN lgas l ON w.lga_id = l.id WHERE l.state_id = s.id AND pu.is_active = 1) as total_pus,
                (SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'state' AND u.jurisdiction_id = s.id AND u.status = 'active') as coordinators,
                (SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = ? AND r.level = 'pu_agent' AND u.jurisdiction_id IN (SELECT id FROM polling_units WHERE ward_id IN (SELECT id FROM wards WHERE lga_id IN (SELECT id FROM lgas WHERE state_id = s.id))) AND u.status = 'active') as agents,
                (SELECT COUNT(*) FROM elections e WHERE e.tenant_id = ? AND e.deleted_at IS NULL AND JSON_CONTAINS(e.states_json, JSON_QUOTE(s.id))) as election_count,
                (SELECT COUNT(*) FROM results_ec8a r JOIN polling_units pu ON r.pu_id = pu.id JOIN wards w ON pu.ward_id = w.id JOIN lgas l ON w.lga_id = l.id WHERE r.tenant_id = ? AND l.state_id = s.id) as total_results,
                (SELECT COUNT(*) FROM results_ec8a r JOIN polling_units pu ON r.pu_id = pu.id JOIN wards w ON pu.ward_id = w.id JOIN lgas l ON w.lga_id = l.id WHERE r.tenant_id = ? AND l.state_id = s.id AND r.status = 'verified') as verified_results,
                (SELECT COUNT(*) FROM results_ec8a r JOIN polling_units pu ON r.pu_id = pu.id JOIN wards w ON pu.ward_id = w.id JOIN lgas l ON w.lga_id = l.id WHERE r.tenant_id = ? AND l.state_id = s.id AND r.status = 'pending') as pending_results,
                (SELECT COUNT(*) FROM results_ec8a r JOIN polling_units pu ON r.pu_id = pu.id JOIN wards w ON pu.ward_id = w.id JOIN lgas l ON w.lga_id = l.id WHERE r.tenant_id = ? AND l.state_id = s.id AND r.status = 'flagged') as flagged_results,
                (SELECT COUNT(*) FROM incidents i WHERE i.tenant_id = ? AND i.state_id = s.id) as total_incidents,
                (SELECT COUNT(*) FROM incidents i WHERE i.tenant_id = ? AND i.state_id = s.id AND i.status IN ('reported', 'investigating')) as open_incidents,
                (SELECT COUNT(*) FROM incidents i WHERE i.tenant_id = ? AND i.state_id = s.id AND i.severity = 'critical') as critical_incidents
            FROM states s
            WHERE s.id IN ($placeholders)
            ORDER BY s.name ASC
        ");
        
        $query_params = array_merge(
            [$tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id, $tenant_id],
            [$tenant_id, $tenant_id, $tenant_id],
            $selected_states
        );
        $stmt->execute($query_params);
        $comparison_data = $stmt->fetchAll();
        
        // Add region info
        foreach ($comparison_data as &$state) {
            $state['region'] = 'Unknown';
            foreach ($regions as $region_name => $region_states) {
                if (in_array($state['name'], $region_states)) {
                    $state['region'] = $region_name;
                    break;
                }
            }
            $state['progress'] = $state['total_pus'] > 0 
                ? min(100, round(($state['verified_results'] / $state['total_pus']) * 100)) 
                : 0;
        }
        
    } catch (Exception $e) {
        error_log("State Comparison Error: " . $e->getMessage());
        $comparison_data = [];
    }
}

// ============================================================
// GET MAX VALUES FOR COMPARISON SCALING
// ============================================================
$max_values = [
    'total_pus' => 0,
    'verified_results' => 0,
    'total_results' => 0,
    'total_voters' => 0,
    'agents' => 0,
    'coordinators' => 0,
    'total_incidents' => 0
];

foreach ($comparison_data as $state) {
    foreach ($max_values as $key => &$value) {
        if (isset($state[$key]) && $state[$key] > $value) {
            $value = $state[$key];
        }
    }
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Compare States';
$page_subtitle = 'Side-by-side state comparison';
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
                <span style="font-weight:600;color:var(--gray-800);">Compare States</span>
            </div>
            
            <h2 style="font-size:1.5rem;font-weight:700;margin:8px 0 0;">Compare States</h2>
            <p style="color:var(--gray-500);margin:2px 0 0;">Select up to 6 states to compare side-by-side</p>
        </div>

        <!-- State Selector -->
        <div style="background:white;border-radius:var(--radius);padding:16px 20px;margin-bottom:20px;border:1px solid var(--gray-200);">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
                <div style="flex:1;min-width:200px;">
                    <label style="font-size:0.75rem;font-weight:600;color:var(--gray-500);display:block;margin-bottom:4px;">Select States to Compare</label>
                    <select name="states[]" multiple class="form-select" style="width:100%;padding:8px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.8rem;background:white;min-height:100px;">
                        <?php foreach ($all_states as $state): ?>
                            <option value="<?php echo $state['id']; ?>" <?php echo in_array($state['id'], $selected_states) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($state['name']); ?> (<?php echo htmlspecialchars($state['capital'] ?? 'N/A'); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div style="font-size:0.65rem;color:var(--gray-400);margin-top:4px;">
                        Hold Ctrl/Cmd to select multiple states (max 6)
                    </div>
                </div>
                
                <div style="display:flex;gap:8px;align-self:flex-end;">
                    <button type="submit" class="btn-primary" style="padding:8px 24px;background:var(--primary);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.8rem;cursor:pointer;transition:var(--transition);">
                        <i class="fas fa-chart-bar"></i> Compare
                    </button>
                    <?php if (!empty($selected_states)): ?>
                        <a href="state-comparison.php" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if (!empty($comparison_data)): ?>
            <!-- Comparison Table -->
            <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);margin-bottom:20px;">
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                        <thead style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
                            <tr>
                                <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);min-width:140px;">Metric</th>
                                <?php foreach ($comparison_data as $state): ?>
                                    <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-600);min-width:120px;">
                                        <?php echo htmlspecialchars($state['name']); ?>
                                        <div style="font-size:0.65rem;font-weight:400;color:var(--gray-400);">
                                            <?php echo htmlspecialchars($state['region']); ?>
                                        </div>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Capital -->
                            <tr style="border-bottom:1px solid var(--gray-100);">
                                <td style="padding:10px 16px;font-weight:500;color:var(--gray-700);">
                                    <i class="fas fa-city" style="color:var(--primary);width:18px;"></i> Capital
                                </td>
                                <?php foreach ($comparison_data as $state): ?>
                                    <td style="padding:10px 16px;text-align:center;">
                                        <?php echo htmlspecialchars($state['capital'] ?? 'N/A'); ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- LGAs -->
                            <tr style="border-bottom:1px solid var(--gray-100);">
                                <td style="padding:10px 16px;font-weight:500;color:var(--gray-700);">
                                    <i class="fas fa-map-marker-alt" style="color:var(--primary);width:18px;"></i> LGAs
                                </td>
                                <?php foreach ($comparison_data as $state): ?>
                                    <td style="padding:10px 16px;text-align:center;font-weight:600;">
                                        <?php echo number_format($state['total_lgas']); ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- Wards -->
                            <tr style="border-bottom:1px solid var(--gray-100);">
                                <td style="padding:10px 16px;font-weight:500;color:var(--gray-700);">
                                    <i class="fas fa-layer-group" style="color:var(--primary);width:18px;"></i> Wards
                                </td>
                                <?php foreach ($comparison_data as $state): ?>
                                    <td style="padding:10px 16px;text-align:center;font-weight:600;">
                                        <?php echo number_format($state['total_wards']); ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- Polling Units -->
                            <tr style="border-bottom:1px solid var(--gray-100);">
                                <td style="padding:10px 16px;font-weight:500;color:var(--gray-700);">
                                    <i class="fas fa-flag-checkered" style="color:var(--primary);width:18px;"></i> Polling Units
                                </td>
                                <?php foreach ($comparison_data as $state): ?>
                                    <td style="padding:10px 16px;text-align:center;font-weight:600;">
                                        <?php echo number_format($state['total_pus']); ?>
                                        <?php if ($max_values['total_pus'] > 0): ?>
                                            <div style="width:100%;height:4px;background:var(--gray-200);border-radius:4px;overflow:hidden;margin-top:4px;">
                                                <div style="width:<?php echo ($state['total_pus'] / $max_values['total_pus']) * 100; ?>%;height:100%;background:#3B82F6;border-radius:4px;"></div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- Registered Voters -->
                            <tr style="border-bottom:1px solid var(--gray-100);">
                                <td style="padding:10px 16px;font-weight:500;color:var(--gray-700);">
                                    <i class="fas fa-users" style="color:var(--primary);width:18px;"></i> Registered Voters
                                </td>
                                <?php foreach ($comparison_data as $state): ?>
                                    <td style="padding:10px 16px;text-align:center;font-weight:600;">
                                        <?php echo number_format($state['total_voters']); ?>
                                        <?php if ($max_values['total_voters'] > 0): ?>
                                            <div style="width:100%;height:4px;background:var(--gray-200);border-radius:4px;overflow:hidden;margin-top:4px;">
                                                <div style="width:<?php echo ($state['total_voters'] / $max_values['total_voters']) * 100; ?>%;height:100%;background:#8B5CF6;border-radius:4px;"></div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- Coordinators -->
                            <tr style="border-bottom:1px solid var(--gray-100);">
                                <td style="padding:10px 16px;font-weight:500;color:var(--gray-700);">
                                    <i class="fas fa-user-tie" style="color:var(--primary);width:18px;"></i> State Coordinators
                                </td>
                                <?php foreach ($comparison_data as $state): ?>
                                    <td style="padding:10px 16px;text-align:center;font-weight:600;color:var(--primary);">
                                        <?php echo number_format($state['coordinators']); ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- Agents -->
                            <tr style="border-bottom:1px solid var(--gray-100);">
                                <td style="padding:10px 16px;font-weight:500;color:var(--gray-700);">
                                    <i class="fas fa-user" style="color:var(--primary);width:18px;"></i> PU Agents
                                </td>
                                <?php foreach ($comparison_data as $state): ?>
                                    <td style="padding:10px 16px;text-align:center;font-weight:600;color:var(--secondary);">
                                        <?php echo number_format($state['agents']); ?>
                                        <?php if ($max_values['agents'] > 0): ?>
                                            <div style="width:100%;height:4px;background:var(--gray-200);border-radius:4px;overflow:hidden;margin-top:4px;">
                                                <div style="width:<?php echo ($state['agents'] / $max_values['agents']) * 100; ?>%;height:100%;background:#10B981;border-radius:4px;"></div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- Elections -->
                            <tr style="border-bottom:1px solid var(--gray-100);">
                                <td style="padding:10px 16px;font-weight:500;color:var(--gray-700);">
                                    <i class="fas fa-vote-yea" style="color:var(--primary);width:18px;"></i> Elections
                                </td>
                                <?php foreach ($comparison_data as $state): ?>
                                    <td style="padding:10px 16px;text-align:center;font-weight:600;">
                                        <?php echo number_format($state['election_count']); ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- Results -->
                            <tr style="border-bottom:1px solid var(--gray-100);">
                                <td style="padding:10px 16px;font-weight:500;color:var(--gray-700);">
                                    <i class="fas fa-file-alt" style="color:var(--primary);width:18px;"></i> Total Results
                                </td>
                                <?php foreach ($comparison_data as $state): ?>
                                    <td style="padding:10px 16px;text-align:center;font-weight:600;">
                                        <?php echo number_format($state['total_results']); ?>
                                        <?php if ($max_values['total_results'] > 0): ?>
                                            <div style="width:100%;height:4px;background:var(--gray-200);border-radius:4px;overflow:hidden;margin-top:4px;">
                                                <div style="width:<?php echo ($state['total_results'] / $max_values['total_results']) * 100; ?>%;height:100%;background:#F59E0B;border-radius:4px;"></div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- Verified Results -->
                            <tr style="border-bottom:1px solid var(--gray-100);background:#F0FDF4;">
                                <td style="padding:10px 16px;font-weight:500;color:var(--gray-700);">
                                    <i class="fas fa-check-circle" style="color:#10B981;width:18px;"></i> Verified Results
                                </td>
                                <?php foreach ($comparison_data as $state): ?>
                                    <td style="padding:10px 16px;text-align:center;font-weight:600;color:#10B981;">
                                        <?php echo number_format($state['verified_results']); ?>
                                        <?php if ($max_values['verified_results'] > 0): ?>
                                            <div style="width:100%;height:4px;background:var(--gray-200);border-radius:4px;overflow:hidden;margin-top:4px;">
                                                <div style="width:<?php echo ($state['verified_results'] / $max_values['verified_results']) * 100; ?>%;height:100%;background:#10B981;border-radius:4px;"></div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- Progress -->
                            <tr style="border-bottom:1px solid var(--gray-100);">
                                <td style="padding:10px 16px;font-weight:500;color:var(--gray-700);">
                                    <i class="fas fa-chart-line" style="color:var(--primary);width:18px;"></i> Progress
                                </td>
                                <?php foreach ($comparison_data as $state): 
                                    $progress_color = $state['progress'] >= 80 ? '#10B981' : ($state['progress'] >= 50 ? '#F59E0B' : '#EF4444');
                                ?>
                                    <td style="padding:10px 16px;text-align:center;">
                                        <div style="display:flex;flex-direction:column;align-items:center;gap:4px;">
                                            <div style="width:100%;height:8px;background:var(--gray-200);border-radius:4px;overflow:hidden;">
                                                <div style="width:<?php echo $state['progress']; ?>%;height:100%;background:<?php echo $progress_color; ?>;border-radius:4px;"></div>
                                            </div>
                                            <span style="font-weight:600;color:<?php echo $progress_color; ?>;">
                                                <?php echo $state['progress']; ?>%
                                            </span>
                                            <span style="font-size:0.6rem;color:var(--gray-400);">
                                                <?php echo number_format($state['pending_results']); ?> pending
                                            </span>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- Incidents -->
                            <tr style="border-bottom:1px solid var(--gray-100);">
                                <td style="padding:10px 16px;font-weight:500;color:var(--gray-700);">
                                    <i class="fas fa-exclamation-triangle" style="color:#EF4444;width:18px;"></i> Total Incidents
                                </td>
                                <?php foreach ($comparison_data as $state): ?>
                                    <td style="padding:10px 16px;text-align:center;">
                                        <div style="font-weight:600;<?php echo $state['total_incidents'] > 0 ? 'color:#EF4444;' : 'color:var(--gray-400);'; ?>">
                                            <?php echo number_format($state['total_incidents']); ?>
                                        </div>
                                        <?php if ($state['critical_incidents'] > 0): ?>
                                            <div style="font-size:0.65rem;color:#EF4444;">
                                                🔴 <?php echo $state['critical_incidents']; ?> critical
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($state['open_incidents'] > 0): ?>
                                            <div style="font-size:0.6rem;color:var(--warning);">
                                                <?php echo $state['open_incidents']; ?> open
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Actions -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                <a href="reports.php?type=state_comparison&states=<?php echo implode(',', $selected_states); ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                    <i class="fas fa-file-pdf" style="color:var(--danger);"></i>
                    <span>Export Comparison Report</span>
                </a>
                <a href="broadcasts-create.php?target=states&states=<?php echo implode(',', $selected_states); ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                    <i class="fas fa-bullhorn" style="color:var(--warning);"></i>
                    <span>Broadcast to Selected States</span>
                </a>
            </div>
            
        <?php elseif (!empty($selected_states)): ?>
            <div style="background:white;border-radius:var(--radius);padding:40px;text-align:center;border:1px solid var(--gray-200);">
                <i class="fas fa-search" style="font-size:2rem;color:var(--gray-300);display:block;margin-bottom:12px;"></i>
                <p style="color:var(--gray-500);">No data found for the selected states</p>
                <a href="state-comparison.php" class="btn-primary" style="padding:8px 24px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-block;margin-top:12px;">
                    <i class="fas fa-undo"></i> Reset Selection
                </a>
            </div>
        <?php else: ?>
            <div style="background:white;border-radius:var(--radius);padding:40px;text-align:center;border:1px solid var(--gray-200);">
                <i class="fas fa-chart-bar" style="font-size:2rem;color:var(--gray-300);display:block;margin-bottom:12px;"></i>
                <p style="color:var(--gray-500);">Select at least 2 states above to compare</p>
                <p style="font-size:0.8rem;color:var(--gray-400);">Hold Ctrl/Cmd to select multiple states</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
.btn-sm:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.quick-action-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
.form-select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1); }

@media (max-width: 768px) {
    table { font-size: 0.7rem; }
    th, td { padding: 6px 8px !important; }
    .stats-grid { grid-template-columns: 1fr 1fr; }
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