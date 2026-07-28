<?php
// ============================================================
// SENATORIAL COORDINATOR - POLLING UNIT DETAILS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'senatorial') {
    header('Location: ../client-admin/');
    exit();
}

$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$senatorial_id = SessionManager::get('senatorial_id');
$state_id = SessionManager::get('state_id');
$db = getDB();

// Get PU ID from URL
$pu_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($pu_id <= 0) {
    header('Location: monitor-district.php');
    exit();
}

// ============================================================
// GET POLLING UNIT DETAILS
// ============================================================
$pu = null;
try {
    $stmt = $db->prepare("
        SELECT 
            pu.*,
            w.name as ward_name,
            w.id as ward_id,
            l.name as lga_name,
            l.id as lga_id,
            s.name as state_name,
            s.id as state_id,
            fc.name as federal_constituency_name,
            sd.name as senatorial_name
        FROM polling_units pu
        JOIN wards w ON pu.ward_id = w.id
        JOIN lgas l ON w.lga_id = l.id
        JOIN states s ON l.state_id = s.id
        LEFT JOIN federal_constituencies fc ON fc.state_id = s.id
        LEFT JOIN senatorial_districts sd ON sd.state_id = s.id
        WHERE pu.id = ? AND pu.is_active = 1
    ");
    $stmt->execute([$pu_id]);
    $pu = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching PU: " . $e->getMessage());
}

if (!$pu) {
    header('Location: monitor-district.php');
    exit();
}

// ============================================================
// GET AGENTS ASSIGNED TO THIS PU
// ============================================================
$agents = [];
try {
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.full_name,
            u.phone,
            u.email,
            u.photograph_url,
            r.name as role_name,
            r.level as role_level,
            aa.assignment_type,
            aa.status as assignment_status,
            aa.assigned_at,
            aa.notes
        FROM agent_assignments aa
        JOIN users u ON aa.user_id = u.id
        JOIN roles r ON u.role_id = r.id
        WHERE aa.pu_id = ? 
        AND aa.tenant_id = ?
        AND aa.status IN ('pending', 'active')
        ORDER BY aa.assigned_at DESC
    ");
    $stmt->execute([$pu_id, $tenant_id]);
    $agents = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching agents: " . $e->getMessage());
}

// ============================================================
// GET ELECTION RESULTS FOR THIS PU
// ============================================================
$results = [];
try {
    $stmt = $db->prepare("
        SELECT 
            r.*,
            e.name as election_name,
            e.type as election_type,
            e.election_date,
            u.full_name as submitted_by_name
        FROM results_ec8a r
        JOIN elections e ON r.election_id = e.id
        LEFT JOIN users u ON r.agent_id = u.id
        WHERE r.pu_id = ? 
        AND r.tenant_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$pu_id, $tenant_id]);
    $results = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching results: " . $e->getMessage());
}

// ============================================================
// GET INCIDENTS FOR THIS PU
// ============================================================
$incidents = [];
try {
    $stmt = $db->prepare("
        SELECT 
            i.*,
            u.full_name as reporter_name
        FROM incidents i
        LEFT JOIN users u ON i.reporter_id = u.id
        WHERE i.pu_id = ? 
        AND i.tenant_id = ?
        ORDER BY i.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$pu_id, $tenant_id]);
    $incidents = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching incidents: " . $e->getMessage());
}

// ============================================================
// GET CHECK-IN HISTORY
// ============================================================
$checkins = [];
try {
    $stmt = $db->prepare("
        SELECT 
            c.*,
            u.full_name as agent_name,
            u.photograph_url as agent_avatar
        FROM agent_checkins c
        JOIN users u ON c.agent_id = u.id
        WHERE c.pu_id = ? 
        AND c.tenant_id = ?
        ORDER BY c.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$pu_id, $tenant_id]);
    $checkins = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching checkins: " . $e->getMessage());
}

// ============================================================
// GET PU STATISTICS
// ============================================================
$stats = [
    'total_agents' => count($agents),
    'total_results' => count($results),
    'total_incidents' => count($incidents),
    'total_checkins' => count($checkins),
    'last_result' => null,
    'last_checkin' => null
];

if (!empty($results)) {
    $stats['last_result'] = $results[0]['created_at'] ?? null;
}
if (!empty($checkins)) {
    $stats['last_checkin'] = $checkins[0]['created_at'] ?? null;
}

$page_title = 'Polling Unit Details';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.pu-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 30px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 16px;
}
.pu-header .header-left h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.pu-header .header-left .subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin-top: 4px;
}
.pu-header .header-left .subtitle i {
    margin: 0 4px;
    font-size: 0.6rem;
    color: var(--gray-300);
}
.pu-header .header-right {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.pu-header .header-right .badge-status {
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}
.badge-status.active { background: #D1FAE5; color: #059669; }
.badge-status.inactive { background: #FEE2E2; color: #DC2626; }
.badge-status.rural { background: #DBEAFE; color: #2563EB; }
.badge-status.urban { background: #EDE9FE; color: #7C3AED; }

.stats-grid-small {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}
.stat-card-small {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 18px;
    text-align: center;
}
.stat-card-small .number {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--gray-800);
}
.stat-card-small .label {
    font-size: 0.7rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-tabs {
    display: flex;
    gap: 4px;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 4px;
    margin-bottom: 24px;
    overflow-x: auto;
}
.detail-tabs .tab-btn {
    padding: 10px 20px;
    border: none;
    background: transparent;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--gray-600);
    cursor: pointer;
    transition: var(--transition);
    white-space: nowrap;
}
.detail-tabs .tab-btn:hover {
    background: var(--gray-50);
    color: var(--gray-800);
}
.detail-tabs .tab-btn.active {
    background: var(--primary);
    color: white;
}
.detail-tabs .tab-btn .count {
    display: inline-block;
    background: var(--gray-200);
    color: var(--gray-600);
    padding: 0 8px;
    border-radius: 10px;
    font-size: 0.7rem;
    margin-left: 4px;
}
.detail-tabs .tab-btn.active .count {
    background: rgba(255,255,255,0.3);
    color: white;
}

.tab-content {
    display: none;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
}
.tab-content.active {
    display: block;
}

.table-responsive {
    overflow-x: auto;
}
.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.table th {
    text-align: left;
    padding: 10px 12px;
    background: var(--gray-50);
    font-weight: 600;
    color: var(--gray-600);
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--gray-200);
}
.table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.table tr:hover td {
    background: var(--gray-50);
}
.table .agent-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    overflow: hidden;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
}
.table .agent-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.status-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 6px;
}
.status-dot.verified { background: #10B981; }
.status-dot.pending { background: #F59E0B; }
.status-dot.rejected { background: #EF4444; }
.status-dot.active { background: #10B981; }
.status-dot.inactive { background: #6B7280; }

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--gray-500);
}
.empty-state i {
    font-size: 2.5rem;
    color: var(--gray-300);
    margin-bottom: 12px;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.info-item {
    display: flex;
    flex-direction: column;
}
.info-item .label {
    font-size: 0.7rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.info-item .value {
    font-size: 0.95rem;
    font-weight: 500;
    color: var(--gray-800);
    margin-top: 2px;
}

@media (max-width: 768px) {
    .pu-header {
        flex-direction: column;
    }
    .stats-grid-small {
        grid-template-columns: repeat(2, 1fr);
    }
    .info-grid {
        grid-template-columns: 1fr;
    }
    .detail-tabs .tab-btn {
        padding: 8px 14px;
        font-size: 0.75rem;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- PU Header -->
        <div class="pu-header">
            <div class="header-left">
                <h2>
                    <i class="fas fa-map-pin" style="color:var(--primary);"></i>
                    <?php echo htmlspecialchars($pu['name'] ?? 'Polling Unit'); ?>
                </h2>
                <div class="subtitle">
                    <?php echo htmlspecialchars($pu['code'] ?? 'N/A'); ?>
                    <i class="fas fa-chevron-right"></i>
                    <?php echo htmlspecialchars($pu['ward_name'] ?? 'N/A'); ?>
                    <i class="fas fa-chevron-right"></i>
                    <?php echo htmlspecialchars($pu['lga_name'] ?? 'N/A'); ?>
                    <i class="fas fa-chevron-right"></i>
                    <?php echo htmlspecialchars($pu['state_name'] ?? 'N/A'); ?>
                </div>
            </div>
            <div class="header-right">
                <span class="badge-status <?php echo ($pu['is_active'] ?? 1) ? 'active' : 'inactive'; ?>">
                    <?php echo ($pu['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?>
                </span>
                <span class="badge-status <?php echo ($pu['is_rural'] ?? 0) ? 'rural' : 'urban'; ?>">
                    <?php echo ($pu['is_rural'] ?? 0) ? 'Rural' : 'Urban'; ?>
                </span>
                <?php if (!empty($pu['network_quality'])): ?>
                    <span class="badge-status" style="background:#FEF3C7;color:#D97706;">
                        <i class="fas fa-signal"></i> <?php echo strtoupper($pu['network_quality']); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid-small">
            <div class="stat-card-small">
                <div class="number"><?php echo number_format($stats['total_agents']); ?></div>
                <div class="label">Agents</div>
            </div>
            <div class="stat-card-small">
                <div class="number"><?php echo number_format($stats['total_results']); ?></div>
                <div class="label">Results</div>
            </div>
            <div class="stat-card-small">
                <div class="number"><?php echo number_format($stats['total_incidents']); ?></div>
                <div class="label">Incidents</div>
            </div>
            <div class="stat-card-small">
                <div class="number"><?php echo number_format($stats['total_checkins']); ?></div>
                <div class="label">Check-ins</div>
            </div>
            <div class="stat-card-small">
                <div class="number"><?php echo number_format($pu['registered_voters'] ?? 0); ?></div>
                <div class="label">Registered Voters</div>
            </div>
            <div class="stat-card-small">
                <div class="number"><?php echo number_format($pu['accredited_voters'] ?? 0); ?></div>
                <div class="label">Accredited Voters</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="detail-tabs">
            <button class="tab-btn active" data-tab="info">
                <i class="fas fa-info-circle"></i> Information
            </button>
            <button class="tab-btn" data-tab="agents">
                <i class="fas fa-user-tie"></i> Agents
                <span class="count"><?php echo count($agents); ?></span>
            </button>
            <button class="tab-btn" data-tab="results">
                <i class="fas fa-file-alt"></i> Results
                <span class="count"><?php echo count($results); ?></span>
            </button>
            <button class="tab-btn" data-tab="incidents">
                <i class="fas fa-exclamation-triangle"></i> Incidents
                <span class="count"><?php echo count($incidents); ?></span>
            </button>
            <button class="tab-btn" data-tab="checkins">
                <i class="fas fa-clock"></i> Check-ins
                <span class="count"><?php echo count($checkins); ?></span>
            </button>
        </div>

        <!-- Tab: Information -->
        <div class="tab-content active" id="tab-info">
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Polling Unit Name</span>
                    <span class="value"><?php echo htmlspecialchars($pu['name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Code</span>
                    <span class="value"><?php echo htmlspecialchars($pu['code'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Ward</span>
                    <span class="value"><?php echo htmlspecialchars($pu['ward_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">LGA</span>
                    <span class="value"><?php echo htmlspecialchars($pu['lga_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">State</span>
                    <span class="value"><?php echo htmlspecialchars($pu['state_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Senatorial District</span>
                    <span class="value"><?php echo htmlspecialchars($pu['senatorial_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Federal Constituency</span>
                    <span class="value"><?php echo htmlspecialchars($pu['federal_constituency_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Type</span>
                    <span class="value"><?php echo ($pu['is_rural'] ?? 0) ? 'Rural' : 'Urban'; ?></span>
                </div>
                <?php if (!empty($pu['address'])): ?>
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <span class="label">Address</span>
                        <span class="value"><?php echo htmlspecialchars($pu['address']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($pu['description'])): ?>
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <span class="label">Description</span>
                        <span class="value"><?php echo htmlspecialchars($pu['description']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($pu['gps_lat']) && !empty($pu['gps_lng'])): ?>
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <span class="label">GPS Location</span>
                        <span class="value">
                            <a href="https://maps.google.com/?q=<?php echo $pu['gps_lat']; ?>,<?php echo $pu['gps_lng']; ?>" 
                               target="_blank" style="color:var(--primary);text-decoration:none;">
                                <i class="fas fa-map-marker-alt"></i> 
                                <?php echo $pu['gps_lat']; ?>, <?php echo $pu['gps_lng']; ?>
                            </a>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab: Agents -->
        <div class="tab-content" id="tab-agents">
            <?php if (count($agents) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Agent</th>
                                <th>Role</th>
                                <th>Assignment Type</th>
                                <th>Status</th>
                                <th>Assigned At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agents as $agent): ?>
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <div class="agent-avatar">
                                                <?php if (!empty($agent['photograph_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($agent['photograph_url']); ?>" alt="">
                                                <?php else: ?>
                                                    <?php echo substr($agent['first_name'] ?? 'U', 0, 1) . substr($agent['last_name'] ?? 'R', 0, 1); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:500;"><?php echo htmlspecialchars($agent['full_name'] ?? 'N/A'); ?></div>
                                                <div style="font-size:0.75rem;color:var(--gray-400);">
                                                    <?php echo htmlspecialchars($agent['email'] ?? ''); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-size:0.75rem;background:var(--gray-100);padding:2px 10px;border-radius:12px;">
                                            <?php echo htmlspecialchars($agent['role_name'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-size:0.75rem;text-transform:capitalize;">
                                            <?php echo str_replace('_', ' ', $agent['assignment_type'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-dot <?php echo ($agent['assignment_status'] ?? 'pending') === 'active' ? 'active' : 'pending'; ?>"></span>
                                        <?php echo ucfirst($agent['assignment_status'] ?? 'Pending'); ?>
                                    </td>
                                    <td style="font-size:0.8rem;color:var(--gray-500);">
                                        <?php echo !empty($agent['assigned_at']) ? date('M d, Y g:i A', strtotime($agent['assigned_at'])) : 'N/A'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-plus"></i>
                    <p>No agents assigned to this polling unit.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab: Results -->
        <div class="tab-content" id="tab-results">
            <?php if (count($results) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Election</th>
                                <th>Submitted By</th>
                                <th>Valid Votes</th>
                                <th>Rejected</th>
                                <th>Status</th>
                                <th>Submitted At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:500;"><?php echo htmlspecialchars($result['election_name'] ?? 'N/A'); ?></div>
                                        <div style="font-size:0.7rem;color:var(--gray-400);text-transform:capitalize;">
                                            <?php echo str_replace('_', ' ', $result['election_type'] ?? ''); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($result['submitted_by_name'] ?? 'Unknown'); ?></td>
                                    <td><?php echo number_format($result['valid_votes'] ?? 0); ?></td>
                                    <td><?php echo number_format($result['rejected_votes'] ?? 0); ?></td>
                                    <td>
                                        <span class="status-dot <?php echo $result['status'] ?? 'pending'; ?>"></span>
                                        <?php echo ucfirst($result['status'] ?? 'Pending'); ?>
                                    </td>
                                    <td style="font-size:0.8rem;color:var(--gray-500);">
                                        <?php echo !empty($result['created_at']) ? date('M d, Y g:i A', strtotime($result['created_at'])) : 'N/A'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <p>No results submitted for this polling unit.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab: Incidents -->
        <div class="tab-content" id="tab-incidents">
            <?php if (count($incidents) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Severity</th>
                                <th>Reported By</th>
                                <th>Status</th>
                                <th>Reported At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incidents as $incident): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:500;"><?php echo htmlspecialchars($incident['title'] ?? 'N/A'); ?></div>
                                        <?php if ($incident['is_panic'] ?? 0): ?>
                                            <span style="font-size:0.65rem;background:#FEE2E2;color:#DC2626;padding:1px 8px;border-radius:10px;">
                                                <i class="fas fa-exclamation-circle"></i> PANIC
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:0.8rem;text-transform:capitalize;">
                                        <?php echo str_replace('_', ' ', $incident['incident_type'] ?? 'N/A'); ?>
                                    </td>
                                    <td>
                                        <span style="padding:2px 10px;border-radius:12px;font-size:0.7rem;
                                            <?php 
                                                $sev = $incident['severity'] ?? 'low';
                                                if ($sev === 'critical') echo 'background:#FEE2E2;color:#DC2626;';
                                                elseif ($sev === 'high') echo 'background:#FEF3C7;color:#D97706;';
                                                elseif ($sev === 'medium') echo 'background:#FEF3C7;color:#D97706;';
                                                else echo 'background:#D1FAE5;color:#059669;';
                                            ?>">
                                            <?php echo ucfirst($incident['severity'] ?? 'Low'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($incident['reporter_name'] ?? 'Unknown'); ?></td>
                                    <td>
                                        <span class="status-dot <?php echo $incident['status'] ?? 'reported'; ?>"></span>
                                        <?php echo ucfirst($incident['status'] ?? 'Reported'); ?>
                                    </td>
                                    <td style="font-size:0.8rem;color:var(--gray-500);">
                                        <?php echo !empty($incident['created_at']) ? date('M d, Y g:i A', strtotime($incident['created_at'])) : 'N/A'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle" style="color:#10B981;"></i>
                    <p>No incidents reported for this polling unit.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab: Check-ins -->
        <div class="tab-content" id="tab-checkins">
            <?php if (count($checkins) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Agent</th>
                                <th>Check-in Type</th>
                                <th>GPS Location</th>
                                <th>Device</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($checkins as $checkin): ?>
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <div class="agent-avatar" style="width:28px;height:28px;font-size:0.65rem;">
                                                <?php if (!empty($checkin['agent_avatar'])): ?>
                                                    <img src="<?php echo htmlspecialchars($checkin['agent_avatar']); ?>" alt="">
                                                <?php else: ?>
                                                    <?php echo substr($checkin['agent_name'] ?? 'A', 0, 1); ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php echo htmlspecialchars($checkin['agent_name'] ?? 'Unknown'); ?>
                                        </div>
                                    </td>
                                    <td style="font-size:0.8rem;text-transform:capitalize;">
                                        <?php echo str_replace('_', ' ', $checkin['checkin_type'] ?? 'N/A'); ?>
                                    </td>
                                    <td style="font-size:0.75rem;">
                                        <?php if (!empty($checkin['gps_lat']) && !empty($checkin['gps_lng'])): ?>
                                            <a href="https://maps.google.com/?q=<?php echo $checkin['gps_lat']; ?>,<?php echo $checkin['gps_lng']; ?>" 
                                               target="_blank" style="color:var(--primary);text-decoration:none;">
                                                <i class="fas fa-map-marker-alt"></i> View
                                            </a>
                                            <?php if (!empty($checkin['gps_distance_from_pu'])): ?>
                                                <span style="color:var(--gray-400);font-size:0.65rem;">
                                                    (<?php echo number_format($checkin['gps_distance_from_pu'], 1); ?>m)
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">No GPS</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:0.75rem;color:var(--gray-500);">
                                        <?php if (!empty($checkin['device_id'])): ?>
                                            <?php echo substr($checkin['device_id'], 0, 12) . '...'; ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                        <?php if (!empty($checkin['device_battery'])): ?>
                                            <span style="font-size:0.65rem;">
                                                🔋 <?php echo $checkin['device_battery']; ?>%
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:0.8rem;color:var(--gray-500);">
                                        <?php echo !empty($checkin['created_at']) ? date('M d, Y g:i A', strtotime($checkin['created_at'])) : 'N/A'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-clock"></i>
                    <p>No check-ins recorded for this polling unit.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Tab switching
document.querySelectorAll('.tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        // Remove active from all tabs
        document.querySelectorAll('.tab-btn').forEach(function(b) {
            b.classList.remove('active');
        });
        document.querySelectorAll('.tab-content').forEach(function(c) {
            c.classList.remove('active');
        });
        
        // Activate clicked tab
        this.classList.add('active');
        var tabId = this.dataset.tab;
        document.getElementById('tab-' + tabId).classList.add('active');
    });
});

// Sidebar toggle
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

window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});
</script>
</body>
</html>