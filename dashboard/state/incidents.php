<?php
// ============================================================
// STATE COORDINATOR - INCIDENTS LIST
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

if (empty($state_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT state_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['state_id'])) {
            $state_id = $user['state_id'];
            SessionManager::set('state_id', $state_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching state_id: " . $e->getMessage());
    }
}

$db = getDB();
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$election_filter = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;
$lga_filter = isset($_GET['lga_id']) ? (int)$_GET['lga_id'] : 0;

// Get state name
$state_name = 'Unknown State';
if (!empty($state_id)) {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    $state_name = $state['name'] ?? 'Unknown State';
}

// Get elections for filter
$elections = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, type, status 
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL
        AND (states_json LIKE ? OR states_json IS NULL OR states_json = '[]')
        ORDER BY election_date DESC
    ");
    $stmt->execute([$tenant_id, '%"' . $state_id . '"%']);
    $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching elections: " . $e->getMessage());
}

// Get LGAs for filter
$lgas = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$state_id]);
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

// Fetch incidents
$incidents = [];
$stats = [
    'total' => 0,
    'reported' => 0,
    'acknowledged' => 0,
    'investigating' => 0,
    'resolved' => 0,
    'escalated' => 0,
    'closed' => 0,
    'false_alarm' => 0,
    'panic' => 0
];

try {
    $sql = "
        SELECT 
            i.*,
            u.first_name as reporter_first_name,
            u.last_name as reporter_last_name,
            pu.name as pu_name,
            pu.code as pu_code,
            w.name as ward_name,
            l.name as lga_name,
            e.name as election_name,
            assigned.first_name as assigned_first_name,
            assigned.last_name as assigned_last_name,
            resolved.first_name as resolved_first_name,
            resolved.last_name as resolved_last_name
        FROM incidents i
        LEFT JOIN users u ON i.reporter_id = u.id
        LEFT JOIN polling_units pu ON i.pu_id = pu.id
        LEFT JOIN wards w ON pu.ward_id = w.id
        LEFT JOIN lgas l ON w.lga_id = l.id
        LEFT JOIN elections e ON i.election_id = e.id
        LEFT JOIN users assigned ON i.assigned_to = assigned.id
        LEFT JOIN users resolved ON i.resolved_by = resolved.id
        WHERE i.tenant_id = ? AND i.state_id = ?
    ";
    
    $params = [$tenant_id, $state_id];
    
    if (!empty($status_filter)) {
        $sql .= " AND i.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($type_filter)) {
        $sql .= " AND i.incident_type = ?";
        $params[] = $type_filter;
    }
    
    if ($election_filter > 0) {
        $sql .= " AND i.election_id = ?";
        $params[] = $election_filter;
    }
    
    if ($lga_filter > 0) {
        $sql .= " AND i.lga_id = ?";
        $params[] = $lga_filter;
    }
    
    $sql .= " ORDER BY i.created_at DESC LIMIT 100";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate stats
    foreach ($incidents as $incident) {
        $stats['total']++;
        $status = $incident['status'] ?? 'reported';
        $stats[$status] = ($stats[$status] ?? 0) + 1;
        if ($incident['is_panic'] == 1) {
            $stats['panic']++;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching incidents: " . $e->getMessage());
}

$incident_types = [
    'violence' => 'Violence',
    'intimidation' => 'Intimidation',
    'ballot_stuffing' => 'Ballot Stuffing',
    'vote_buying' => 'Vote Buying',
    'voter_suppression' => 'Voter Suppression',
    'material_shortage' => 'Material Shortage',
    'delay' => 'Delay',
    'technical_issue' => 'Technical Issue',
    'other' => 'Other',
    'panic_button' => 'Panic Button'
];

$severity_colors = [
    'low' => 'info',
    'medium' => 'warning',
    'high' => 'danger',
    'critical' => 'critical'
];

$status_colors = [
    'reported' => 'warning',
    'acknowledged' => 'info',
    'investigating' => 'primary',
    'resolved' => 'success',
    'escalated' => 'danger',
    'closed' => 'secondary',
    'false_alarm' => 'secondary'
];

$page_title = 'Incidents';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.filter-bar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    align-items: center;
    background: white;
    padding: 16px 20px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}

.filter-bar select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.8rem;
    font-family: 'Inter', sans-serif;
    background: white;
    min-width: 140px;
}

.filter-bar select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.filter-bar .btn-filter {
    padding: 8px 24px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.filter-bar .btn-filter:hover {
    background: var(--primary-dark);
}

.filter-bar .filter-info {
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-left: auto;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}

.stats-row .stat-box {
    background: white;
    border-radius: 10px;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

.stats-row .stat-box .number {
    font-size: 1.2rem;
    font-weight: 700;
}

.stats-row .stat-box .number.total { color: #3B82F6; }
.stats-row .stat-box .number.reported { color: #F59E0B; }
.stats-row .stat-box .number.investigating { color: #8B5CF6; }
.stats-row .stat-box .number.resolved { color: #10B981; }
.stats-row .stat-box .number.escalated { color: #EF4444; }
.stats-row .stat-box .number.panic { color: #DC2626; }

.stats-row .stat-box .label {
    font-size: 0.55rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.incident-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 16px;
}

.incident-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 18px 20px;
    transition: var(--transition);
    position: relative;
}

.incident-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
}

.incident-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.incident-card .card-header .incident-title {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--gray-800);
}

.incident-card .card-header .incident-title .panic-badge {
    background: #EF4444;
    color: white;
    font-size: 0.5rem;
    padding: 2px 8px;
    border-radius: 4px;
    margin-left: 6px;
    animation: pulse-badge 1.5s ease-in-out infinite;
}

@keyframes pulse-badge {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.incident-card .incident-type {
    font-size: 0.6rem;
    color: var(--gray-500);
    background: var(--gray-100);
    padding: 2px 10px;
    border-radius: 12px;
}

.incident-card .incident-description {
    font-size: 0.78rem;
    color: var(--gray-600);
    margin: 6px 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.incident-card .incident-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px;
    margin: 8px 0;
    padding: 8px 0;
    border-top: 1px solid var(--gray-100);
    border-bottom: 1px solid var(--gray-100);
}

.incident-card .incident-meta .meta-item {
    font-size: 0.65rem;
    color: var(--gray-500);
}

.incident-card .incident-meta .meta-item .value {
    font-weight: 500;
    color: var(--gray-700);
}

.incident-card .severity-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.55rem;
    padding: 2px 10px;
    border-radius: 12px;
    font-weight: 600;
}

.incident-card .severity-badge .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.incident-card .severity-badge.low { background: #EFF6FF; color: #1E40AF; }
.incident-card .severity-badge.low .dot { background: #3B82F6; }
.incident-card .severity-badge.medium { background: #FFFBEB; color: #92400E; }
.incident-card .severity-badge.medium .dot { background: #F59E0B; }
.incident-card .severity-badge.high { background: #FEF2F2; color: #991B1B; }
.incident-card .severity-badge.high .dot { background: #EF4444; }
.incident-card .severity-badge.critical { background: #FEF2F2; color: #7F1D1D; border: 1px solid #DC2626; }
.incident-card .severity-badge.critical .dot { background: #DC2626; }

.incident-card .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.55rem;
    padding: 2px 10px;
    border-radius: 12px;
    font-weight: 600;
}

.incident-card .status-badge .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.incident-card .status-badge.reported { background: #FFFBEB; color: #92400E; }
.incident-card .status-badge.reported .dot { background: #F59E0B; }
.incident-card .status-badge.acknowledged { background: #EFF6FF; color: #1E40AF; }
.incident-card .status-badge.acknowledged .dot { background: #3B82F6; }
.incident-card .status-badge.investigating { background: #F5F3FF; color: #5B21B6; }
.incident-card .status-badge.investigating .dot { background: #8B5CF6; }
.incident-card .status-badge.resolved { background: #ECFDF5; color: #065F46; }
.incident-card .status-badge.resolved .dot { background: #10B981; }
.incident-card .status-badge.escalated { background: #FEF2F2; color: #991B1B; }
.incident-card .status-badge.escalated .dot { background: #EF4444; }
.incident-card .status-badge.closed { background: #F3F4F6; color: #6B7280; }
.incident-card .status-badge.closed .dot { background: #9CA3AF; }
.incident-card .status-badge.false_alarm { background: #F3F4F6; color: #6B7280; }
.incident-card .status-badge.false_alarm .dot { background: #9CA3AF; }

.incident-card .card-actions {
    display: flex;
    gap: 6px;
    margin-top: 10px;
    flex-wrap: wrap;
}

.incident-card .card-actions a {
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 0.65rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
}

.incident-card .card-actions .btn-view {
    background: var(--gray-100);
    color: var(--gray-700);
}

.incident-card .card-actions .btn-view:hover {
    background: var(--gray-200);
}

.incident-card .card-actions .btn-update {
    background: #EFF6FF;
    color: #3B82F6;
}

.incident-card .card-actions .btn-update:hover {
    background: #DBEAFE;
}

.incident-card .card-actions .btn-resolve {
    background: #ECFDF5;
    color: #10B981;
}

.incident-card .card-actions .btn-resolve:hover {
    background: #D1FAE5;
}

.incident-card .card-actions .btn-escalate {
    background: #FEF2F2;
    color: #DC2626;
}

.incident-card .card-actions .btn-escalate:hover {
    background: #FEE2E2;
}

.incident-card .card-actions .btn-close {
    background: var(--gray-100);
    color: var(--gray-500);
}

.incident-card .card-actions .btn-close:hover {
    background: var(--gray-200);
}

.empty-state {
    grid-column: 1/-1;
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}

.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    display: block;
    margin-bottom: 12px;
}

.empty-state h3 {
    color: var(--gray-600);
    margin: 0;
}

.empty-state p {
    color: var(--gray-400);
    margin-top: 6px;
}

@media (max-width: 768px) {
    .incident-grid {
        grid-template-columns: 1fr;
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar select {
        width: 100%;
        min-width: unset;
    }
    .filter-bar .filter-info {
        margin-left: 0;
        text-align: center;
    }
    .stats-row {
        grid-template-columns: repeat(3, 1fr);
    }
    .incident-card .incident-meta {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="welcome-section">
            <div>
                <h1><i class="fas fa-exclamation-triangle"></i> Incidents</h1>
                <p class="subtitle">
                    <i class="fas fa-flag"></i> 
                    <?php echo htmlspecialchars($state_name); ?> State - Incident Management
                </p>
            </div>
            <div class="actions">
                <a href="incident-create.php" class="btn-primary-sm">
                    <i class="fas fa-plus"></i> Report Incident
                </a>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="number total"><?php echo $stats['total']; ?></div>
                <div class="label">Total</div>
            </div>
            <div class="stat-box">
                <div class="number reported"><?php echo $stats['reported'] ?? 0; ?></div>
                <div class="label">Reported</div>
            </div>
            <div class="stat-box">
                <div class="number investigating"><?php echo $stats['investigating'] ?? 0; ?></div>
                <div class="label">Investigating</div>
            </div>
            <div class="stat-box">
                <div class="number resolved"><?php echo $stats['resolved'] ?? 0; ?></div>
                <div class="label">Resolved</div>
            </div>
            <div class="stat-box">
                <div class="number escalated"><?php echo $stats['escalated'] ?? 0; ?></div>
                <div class="label">Escalated</div>
            </div>
            <div class="stat-box">
                <div class="number panic"><?php echo $stats['panic'] ?? 0; ?></div>
                <div class="label">Panic</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <select id="statusFilter" onchange="applyFilters()">
                <option value="">All Status</option>
                <option value="reported" <?php echo $status_filter === 'reported' ? 'selected' : ''; ?>>Reported</option>
                <option value="acknowledged" <?php echo $status_filter === 'acknowledged' ? 'selected' : ''; ?>>Acknowledged</option>
                <option value="investigating" <?php echo $status_filter === 'investigating' ? 'selected' : ''; ?>>Investigating</option>
                <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                <option value="escalated" <?php echo $status_filter === 'escalated' ? 'selected' : ''; ?>>Escalated</option>
                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                <option value="false_alarm" <?php echo $status_filter === 'false_alarm' ? 'selected' : ''; ?>>False Alarm</option>
            </select>

            <select id="typeFilter" onchange="applyFilters()">
                <option value="">All Types</option>
                <?php foreach ($incident_types as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo $type_filter === $key ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select id="electionFilter" onchange="applyFilters()">
                <option value="0">All Elections</option>
                <?php foreach ($elections as $e): ?>
                    <option value="<?php echo $e['id']; ?>" <?php echo $election_filter == $e['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($e['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select id="lgaFilter" onchange="applyFilters()">
                <option value="0">All LGAs</option>
                <?php foreach ($lgas as $l): ?>
                    <option value="<?php echo $l['id']; ?>" <?php echo $lga_filter == $l['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($l['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button class="btn-filter" onclick="applyFilters()">
                <i class="fas fa-filter"></i> Apply
            </button>

            <span class="filter-info">
                <i class="fas fa-list"></i> <?php echo count($incidents); ?> incidents found
            </span>
        </div>

        <!-- Incident Grid -->
        <div class="incident-grid">
            <?php foreach ($incidents as $incident): 
                $severity_class = $incident['severity'];
                $status_class = $incident['status'];
                $is_panic = $incident['is_panic'] == 1;
            ?>
                <div class="incident-card">
                    <div class="card-header">
                        <div class="incident-title">
                            <?php echo htmlspecialchars($incident['title']); ?>
                            <?php if ($is_panic): ?>
                                <span class="panic-badge"><i class="fas fa-exclamation-circle"></i> PANIC</span>
                            <?php endif; ?>
                        </div>
                        <span class="incident-type">
                            <?php echo $incident_types[$incident['incident_type']] ?? ucfirst($incident['incident_type']); ?>
                        </span>
                    </div>

                    <div class="incident-description">
                        <?php echo htmlspecialchars(substr($incident['description'], 0, 120)) . (strlen($incident['description']) > 120 ? '...' : ''); ?>
                    </div>

                    <div class="incident-meta">
                        <div class="meta-item">
                            <span class="label">Location</span>
                            <div class="value">
                                <?php echo htmlspecialchars($incident['pu_name'] ?? $incident['ward_name'] ?? $incident['lga_name'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        <div class="meta-item">
                            <span class="label">Election</span>
                            <div class="value"><?php echo htmlspecialchars($incident['election_name'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="meta-item">
                            <span class="label">Reported By</span>
                            <div class="value">
                                <?php echo htmlspecialchars($incident['reporter_first_name'] ?? '') . ' ' . htmlspecialchars($incident['reporter_last_name'] ?? 'Unknown'); ?>
                            </div>
                        </div>
                        <div class="meta-item">
                            <span class="label">Reported At</span>
                            <div class="value"><?php echo date('M j, Y g:i A', strtotime($incident['created_at'])); ?></div>
                        </div>
                    </div>

                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px;">
                        <span class="severity-badge <?php echo $severity_class; ?>">
                            <span class="dot"></span>
                            <?php echo ucfirst($incident['severity']); ?>
                        </span>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <span class="dot"></span>
                            <?php echo ucfirst(str_replace('_', ' ', $incident['status'])); ?>
                        </span>
                        <?php if ($incident['assigned_to']): ?>
                            <span style="font-size:0.55rem;color:var(--gray-500);">
                                <i class="fas fa-user"></i> 
                                <?php echo htmlspecialchars($incident['assigned_first_name'] ?? '') . ' ' . htmlspecialchars($incident['assigned_last_name'] ?? ''); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="card-actions">
                        <a href="incident-view.php?id=<?php echo $incident['id']; ?>" class="btn-view">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <?php if ($incident['status'] === 'reported' || $incident['status'] === 'acknowledged'): ?>
                            <a href="incident-update.php?id=<?php echo $incident['id']; ?>" class="btn-update">
                                <i class="fas fa-edit"></i> Update
                            </a>
                            <a href="incident-escalate.php?id=<?php echo $incident['id']; ?>" class="btn-escalate">
                                <i class="fas fa-arrow-up"></i> Escalate
                            </a>
                        <?php endif; ?>
                        <?php if ($incident['status'] === 'investigating'): ?>
                            <a href="incident-resolve.php?id=<?php echo $incident['id']; ?>" class="btn-resolve">
                                <i class="fas fa-check"></i> Resolve
                            </a>
                            <a href="incident-escalate.php?id=<?php echo $incident['id']; ?>" class="btn-escalate">
                                <i class="fas fa-arrow-up"></i> Escalate
                            </a>
                        <?php endif; ?>
                        <?php if ($incident['status'] === 'resolved'): ?>
                            <a href="incident-close.php?id=<?php echo $incident['id']; ?>" class="btn-close">
                                <i class="fas fa-times"></i> Close
                            </a>
                        <?php endif; ?>
                        <a href="incident-add-notes.php?id=<?php echo $incident['id']; ?>" class="btn-view">
                            <i class="fas fa-sticky-note"></i> Notes
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($incidents)): ?>
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>No Incidents Found</h3>
                    <p>No incidents have been reported in <?php echo htmlspecialchars($state_name); ?> yet.</p>
                    <a href="incident-create.php" class="btn-primary-sm" style="margin-top:12px;">
                        <i class="fas fa-plus"></i> Report Incident
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function applyFilters() {
    var status = document.getElementById('statusFilter').value;
    var type = document.getElementById('typeFilter').value;
    var election = document.getElementById('electionFilter').value;
    var lga = document.getElementById('lgaFilter').value;
    
    var url = window.location.pathname;
    var params = [];
    if (status) params.push('status=' + status);
    if (type) params.push('type=' + type);
    if (election && election !== '0') params.push('election_id=' + election);
    if (lga && lga !== '0') params.push('lga_id=' + lga);
    if (params.length) url += '?' + params.join('&');
    window.location.href = url;
}

// Same sidebar scripts as index.php
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