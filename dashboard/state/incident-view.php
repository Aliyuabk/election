<?php
// ============================================================
// STATE COORDINATOR - INCIDENT VIEW
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

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

// If state_id is not set in session, try to get it from user record
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

// ============================================================
// GENERATE CSRF TOKEN
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ============================================================
// GET INCIDENT ID
// ============================================================
$incident_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($incident_id <= 0) {
    header('Location: incidents.php');
    exit();
}

// ============================================================
// FETCH INCIDENT DETAILS
// ============================================================
$incident = null;
$timeline = [];

try {
    $stmt = $db->prepare("
        SELECT 
            i.*,
            u.first_name as reporter_first,
            u.last_name as reporter_last,
            u.phone as reporter_phone,
            u.email as reporter_email,
            pu.name as pu_name,
            pu.code as pu_code,
            w.name as ward_name,
            l.name as lga_name,
            s.name as state_name,
            assigned.first_name as assigned_first,
            assigned.last_name as assigned_last,
            assigned.email as assigned_email,
            resolved.first_name as resolved_first,
            resolved.last_name as resolved_last,
            e.name as election_name
        FROM incidents i
        LEFT JOIN users u ON i.reporter_id = u.id
        LEFT JOIN polling_units pu ON i.pu_id = pu.id
        LEFT JOIN wards w ON i.ward_id = w.id
        LEFT JOIN lgas l ON i.lga_id = l.id
        LEFT JOIN states s ON i.state_id = s.id
        LEFT JOIN users assigned ON i.assigned_to = assigned.id
        LEFT JOIN users resolved ON i.resolved_by = resolved.id
        LEFT JOIN elections e ON i.election_id = e.id
        WHERE i.id = ? AND i.tenant_id = ?
    ");
    $stmt->execute([$incident_id, $tenant_id]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$incident) {
        header('Location: incidents.php');
        exit();
    }
    
    // Get incident timeline (activity logs related to this incident)
    $stmt = $db->prepare("
        SELECT * FROM activity_logs 
        WHERE entity_type = 'incident' AND entity_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$incident_id]);
    $timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching incident: " . $e->getMessage());
}

// ============================================================
// INCIDENT TYPES AND STATUSES
// ============================================================
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
    'critical' => 'danger',
    'high' => 'warning',
    'medium' => 'warning',
    'low' => 'secondary'
];

$status_colors = [
    'reported' => 'danger',
    'acknowledged' => 'warning',
    'investigating' => 'primary',
    'resolved' => 'success',
    'escalated' => 'danger',
    'false_alarm' => 'secondary'
];

include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.page-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.page-header h2 small {
    font-size: 0.8rem;
    font-weight: 400;
    color: var(--gray-500);
    display: block;
    margin-top: 2px;
}

.btn-secondary-sm {
    padding: 8px 20px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.btn-secondary-sm:hover {
    background: var(--gray-200);
}

.incident-header {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px 24px;
    margin-bottom: 20px;
    box-shadow: var(--shadow-sm);
    position: relative;
}
.incident-header .title {
    font-size: 1.2rem;
    font-weight: 700;
}
.incident-header .title .panic-badge {
    display: inline-block;
    padding: 2px 12px;
    background: #FEF2F2;
    color: #DC2626;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    animation: pulse 1.5s ease-in-out infinite;
    margin-left: 8px;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
.incident-header .subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin-top: 4px;
}
.incident-header .meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 8px;
    font-size: 0.8rem;
    color: var(--gray-500);
}
.incident-header .meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.badge-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
}
.badge-status .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}
.badge-status.danger { background: #FEF2F2; color: #991B1B; }
.badge-status.danger .dot { background: #EF4444; }
.badge-status.warning { background: #FFFBEB; color: #92400E; }
.badge-status.warning .dot { background: #F59E0B; }
.badge-status.success { background: #ECFDF5; color: #065F46; }
.badge-status.success .dot { background: #10B981; }
.badge-status.primary { background: #EFF6FF; color: #1E40AF; }
.badge-status.primary .dot { background: #3B82F6; }
.badge-status.secondary { background: #F3F4F6; color: #6B7280; }
.badge-status.secondary .dot { background: #9CA3AF; }

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px 24px;
    background: var(--gray-50);
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
}
.info-grid .info-item {
    display: flex;
    flex-direction: column;
}
.info-grid .info-item .label {
    font-size: 0.7rem;
    color: var(--gray-400);
    font-weight: 500;
}
.info-grid .info-item .value {
    font-size: 0.9rem;
    color: var(--gray-800);
    font-weight: 500;
}

.description-box {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 20px;
    margin-bottom: 20px;
}
.description-box .title {
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 8px;
}
.description-box .content {
    color: var(--gray-700);
    line-height: 1.6;
    font-size: 0.9rem;
}

.timeline-item {
    display: flex;
    gap: 16px;
    padding: 12px 0;
    border-bottom: 1px solid var(--gray-100);
}
.timeline-item:last-child {
    border-bottom: none;
}
.timeline-item .timeline-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    flex-shrink: 0;
    background: var(--gray-100);
    color: var(--gray-500);
}
.timeline-item .timeline-content {
    flex: 1;
}
.timeline-item .timeline-content .text {
    font-size: 0.8rem;
    color: var(--gray-700);
}
.timeline-item .timeline-content .time {
    font-size: 0.65rem;
    color: var(--gray-400);
}

.actions-section {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 20px;
}
.actions-section .btn {
    padding: 10px 24px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.actions-section .btn-primary {
    background: #3B82F6;
    color: white;
}
.actions-section .btn-primary:hover {
    background: #2563EB;
}
.actions-section .btn-success {
    background: #10B981;
    color: white;
}
.actions-section .btn-success:hover {
    background: #059669;
}
.actions-section .btn-danger {
    background: #EF4444;
    color: white;
}
.actions-section .btn-danger:hover {
    background: #DC2626;
}
.actions-section .btn-warning {
    background: #F59E0B;
    color: white;
}
.actions-section .btn-warning:hover {
    background: #D97706;
}
.actions-section .btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.actions-section .btn-secondary:hover {
    background: var(--gray-200);
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .info-grid {
        grid-template-columns: 1fr;
    }
    .incident-header .meta {
        flex-direction: column;
        gap: 6px;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-exclamation-triangle" style="color:var(--danger);margin-right:8px;"></i>
                    Incident Details
                    <small>View incident information</small>
                </h2>
            </div>
            <div>
                <a href="incidents.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back to Incidents
                </a>
            </div>
        </div>

        <?php if ($incident): ?>
            <!-- Incident Header -->
            <div class="incident-header">
                <div class="title">
                    <?php echo htmlspecialchars($incident['title']); ?>
                    <?php if ($incident['is_panic']): ?>
                        <span class="panic-badge"><i class="fas fa-bell"></i> PANIC</span>
                    <?php endif; ?>
                </div>
                <div class="subtitle">
                    <span class="badge-status <?php echo $status_colors[$incident['status']] ?? 'secondary'; ?>">
                        <span class="dot"></span>
                        <?php echo ucfirst(str_replace('_', ' ', $incident['status'])); ?>
                    </span>
                    <span style="margin-left:12px;">
                        <span class="badge-status <?php echo $severity_colors[$incident['severity']] ?? 'secondary'; ?>">
                            <span class="dot"></span>
                            <?php echo ucfirst($incident['severity']); ?> Severity
                        </span>
                    </span>
                </div>
                <div class="meta">
                    <span><i class="fas fa-tag"></i> <?php echo $incident_types[$incident['incident_type']] ?? ucfirst($incident['incident_type']); ?></span>
                    <span><i class="fas fa-user"></i> Reported by: <?php echo htmlspecialchars($incident['reporter_first'] . ' ' . $incident['reporter_last']); ?></span>
                    <span><i class="fas fa-clock"></i> <?php echo date('F j, Y g:i A', strtotime($incident['created_at'])); ?></span>
                    <?php if ($incident['election_name']): ?>
                        <span><i class="fas fa-vote-yea"></i> <?php echo htmlspecialchars($incident['election_name']); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Info Grid -->
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Location</span>
                    <span class="value"><?php echo htmlspecialchars($incident['lga_name'] ?? 'N/A'); ?></span>
                    <span style="font-size:0.75rem;color:var(--gray-400);">
                        <?php echo htmlspecialchars($incident['ward_name'] ?? ''); ?>
                        <?php if ($incident['pu_name']): ?>
                            - <?php echo htmlspecialchars($incident['pu_name']); ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="label">State</span>
                    <span class="value"><?php echo htmlspecialchars($incident['state_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Assigned To</span>
                    <span class="value">
                        <?php if ($incident['assigned_to']): ?>
                            <?php echo htmlspecialchars($incident['assigned_first'] . ' ' . $incident['assigned_last']); ?>
                            <span style="font-size:0.75rem;color:var(--gray-400);display:block;">
                                <?php echo htmlspecialchars($incident['assigned_email'] ?? ''); ?>
                            </span>
                        <?php else: ?>
                            <span style="color:var(--gray-400);">Not assigned</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="label">Resolved</span>
                    <span class="value">
                        <?php if ($incident['resolved_at']): ?>
                            <?php echo date('F j, Y g:i A', strtotime($incident['resolved_at'])); ?>
                            <span style="font-size:0.75rem;color:var(--gray-400);display:block;">
                                By: <?php echo htmlspecialchars($incident['resolved_first'] . ' ' . $incident['resolved_last']); ?>
                            </span>
                        <?php else: ?>
                            <span style="color:var(--gray-400);">Not resolved</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- Description -->
            <div class="description-box">
                <div class="title"><i class="fas fa-align-left"></i> Description</div>
                <div class="content"><?php echo nl2br(htmlspecialchars($incident['description'])); ?></div>
                <?php if ($incident['resolution_notes']): ?>
                    <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--gray-200);">
                        <div style="font-size:0.7rem;color:var(--gray-400);font-weight:600;">Resolution Notes</div>
                        <div style="color:var(--gray-700);font-size:0.85rem;margin-top:4px;">
                            <?php echo nl2br(htmlspecialchars($incident['resolution_notes'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <?php if ($incident['status'] !== 'resolved' && $incident['status'] !== 'false_alarm'): ?>
                <div class="actions-section">
                    <button onclick="assignIncident(<?php echo $incident['id']; ?>)" class="btn btn-primary">
                        <i class="fas fa-user-check"></i> Assign
                    </button>
                    <button onclick="resolveIncident(<?php echo $incident['id']; ?>)" class="btn btn-success">
                        <i class="fas fa-check"></i> Resolve
                    </button>
                    <?php if ($incident['severity'] === 'critical' || $incident['severity'] === 'high'): ?>
                        <button onclick="escalateIncident(<?php echo $incident['id']; ?>)" class="btn btn-danger">
                            <i class="fas fa-arrow-up"></i> Escalate
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Timeline -->
            <div style="background:white;border-radius:var(--radius);border:1px solid var(--gray-200);padding:16px 20px;margin-top:20px;">
                <h4 style="font-size:0.85rem;font-weight:600;margin:0 0 12px 0;padding-bottom:10px;border-bottom:1px solid var(--gray-200);">
                    <i class="fas fa-clock" style="color:var(--primary);margin-right:6px;"></i>
                    Timeline
                </h4>
                <?php if (count($timeline) > 0): ?>
                    <?php foreach ($timeline as $event): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <i class="fas fa-circle"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="text"><?php echo htmlspecialchars($event['description'] ?? 'Activity recorded'); ?></div>
                                <div class="time"><?php echo date('M j, Y g:i A', strtotime($event['created_at'])); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center;padding:20px;color:var(--gray-400);">
                        <i class="fas fa-clock" style="font-size:1.5rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                        <p style="margin:0;font-size:0.85rem;">No timeline events</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() {
            preloader.style.display = 'none';
        }, 600);
    }
});

// ============================================================
// SIDEBAR TOGGLE
// ============================================================
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

// ============================================================
// SIDEBAR DROPDOWNS
// ============================================================
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

// ============================================================
// PROFILE DROPDOWN
// ============================================================
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

// ============================================================
// INCIDENT ACTIONS
// ============================================================
function assignIncident(incidentId) {
    var userId = prompt('Enter the user ID to assign this incident to:');
    if (userId && !isNaN(userId)) {
        if (confirm('Are you sure you want to assign this incident?')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'incident-assign.php';
            
            var input1 = document.createElement('input');
            input1.type = 'hidden';
            input1.name = 'incident_id';
            input1.value = incidentId;
            form.appendChild(input1);
            
            var input2 = document.createElement('input');
            input2.type = 'hidden';
            input2.name = 'user_id';
            input2.value = userId;
            form.appendChild(input2);
            
            var token = document.createElement('input');
            token.type = 'hidden';
            token.name = 'csrf_token';
            token.value = '<?php echo $csrf_token; ?>';
            form.appendChild(token);
            
            document.body.appendChild(form);
            form.submit();
        }
    } else if (userId !== null) {
        alert('Please enter a valid user ID.');
    }
}

function resolveIncident(incidentId) {
    var resolution = prompt('Enter resolution notes:');
    if (resolution !== null) {
        if (confirm('Are you sure you want to mark this incident as resolved?')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'incident-resolve.php';
            
            var input1 = document.createElement('input');
            input1.type = 'hidden';
            input1.name = 'incident_id';
            input1.value = incidentId;
            form.appendChild(input1);
            
            var input2 = document.createElement('input');
            input2.type = 'hidden';
            input2.name = 'resolution_notes';
            input2.value = resolution;
            form.appendChild(input2);
            
            var token = document.createElement('input');
            token.type = 'hidden';
            token.name = 'csrf_token';
            token.value = '<?php echo $csrf_token; ?>';
            form.appendChild(token);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
}

function escalateIncident(incidentId) {
    var reason = prompt('Enter reason for escalation:');
    if (reason !== null) {
        if (confirm('Are you sure you want to escalate this incident?')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'incident-escalate.php';
            
            var input1 = document.createElement('input');
            input1.type = 'hidden';
            input1.name = 'incident_id';
            input1.value = incidentId;
            form.appendChild(input1);
            
            var input2 = document.createElement('input');
            input2.type = 'hidden';
            input2.name = 'reason';
            input2.value = reason;
            form.appendChild(input2);
            
            var token = document.createElement('input');
            token.type = 'hidden';
            token.name = 'csrf_token';
            token.value = '<?php echo $csrf_token; ?>';
            form.appendChild(token);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
}
</script>
</body>
</html>