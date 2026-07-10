<?php
// ============================================================
// STATE COORDINATOR - VIEW INCIDENT DETAILS
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
$incident_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($incident_id <= 0) {
    header('Location: incidents.php');
    exit();
}

// Get incident details
$incident = null;
try {
    $stmt = $db->prepare("
        SELECT 
            i.*,
            u.first_name as reporter_first_name,
            u.last_name as reporter_last_name,
            u.email as reporter_email,
            u.phone as reporter_phone,
            pu.name as pu_name,
            pu.code as pu_code,
            w.name as ward_name,
            l.name as lga_name,
            l.id as lga_id,
            s.name as state_name,
            e.name as election_name,
            e.type as election_type,
            assigned.first_name as assigned_first_name,
            assigned.last_name as assigned_last_name,
            assigned.email as assigned_email,
            resolved.first_name as resolved_first_name,
            resolved.last_name as resolved_last_name
        FROM incidents i
        LEFT JOIN users u ON i.reporter_id = u.id
        LEFT JOIN polling_units pu ON i.pu_id = pu.id
        LEFT JOIN wards w ON pu.ward_id = w.id
        LEFT JOIN lgas l ON w.lga_id = l.id
        LEFT JOIN states s ON l.state_id = s.id
        LEFT JOIN elections e ON i.election_id = e.id
        LEFT JOIN users assigned ON i.assigned_to = assigned.id
        LEFT JOIN users resolved ON i.resolved_by = resolved.id
        WHERE i.id = ? AND i.tenant_id = ? AND i.state_id = ?
    ");
    $stmt->execute([$incident_id, $tenant_id, $state_id]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching incident: " . $e->getMessage());
}

if (!$incident) {
    header('Location: incidents.php');
    exit();
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

$severity_labels = [
    'low' => 'Low',
    'medium' => 'Medium',
    'high' => 'High',
    'critical' => 'Critical'
];

$status_labels = [
    'reported' => 'Reported',
    'acknowledged' => 'Acknowledged',
    'investigating' => 'Investigating',
    'resolved' => 'Resolved',
    'escalated' => 'Escalated',
    'closed' => 'Closed',
    'false_alarm' => 'False Alarm'
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

$severity_colors = [
    'low' => 'info',
    'medium' => 'warning',
    'high' => 'danger',
    'critical' => 'critical'
];

$page_title = 'View Incident';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.incident-container {
    max-width: 900px;
    margin: 0 auto;
}

.incident-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
    margin-bottom: 16px;
}

.incident-card .card-title {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.incident-card .card-title i {
    color: var(--primary);
    margin-right: 6px;
}

.incident-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 12px;
}

.incident-header .title {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--gray-800);
}

.incident-header .title .panic-badge {
    background: #EF4444;
    color: white;
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 4px;
    margin-left: 8px;
    animation: pulse-badge 1.5s ease-in-out infinite;
}

@keyframes pulse-badge {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.detail-item {
    font-size: 0.82rem;
}

.detail-item .label {
    color: var(--gray-500);
    font-size: 0.65rem;
    display: block;
}

.detail-item .value {
    font-weight: 500;
    color: var(--gray-800);
}

.detail-item .value .status-badge {
    font-size: 0.6rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.6rem;
    padding: 3px 12px;
    border-radius: 12px;
    font-weight: 600;
}

.status-badge .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.status-badge.reported { background: #FFFBEB; color: #92400E; }
.status-badge.reported .dot { background: #F59E0B; }
.status-badge.acknowledged { background: #EFF6FF; color: #1E40AF; }
.status-badge.acknowledged .dot { background: #3B82F6; }
.status-badge.investigating { background: #F5F3FF; color: #5B21B6; }
.status-badge.investigating .dot { background: #8B5CF6; }
.status-badge.resolved { background: #ECFDF5; color: #065F46; }
.status-badge.resolved .dot { background: #10B981; }
.status-badge.escalated { background: #FEF2F2; color: #991B1B; }
.status-badge.escalated .dot { background: #EF4444; }
.status-badge.closed { background: #F3F4F6; color: #6B7280; }
.status-badge.closed .dot { background: #9CA3AF; }
.status-badge.false_alarm { background: #F3F4F6; color: #6B7280; }
.status-badge.false_alarm .dot { background: #9CA3AF; }

.severity-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.6rem;
    padding: 3px 12px;
    border-radius: 12px;
    font-weight: 600;
}

.severity-badge .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.severity-badge.low { background: #EFF6FF; color: #1E40AF; }
.severity-badge.low .dot { background: #3B82F6; }
.severity-badge.medium { background: #FFFBEB; color: #92400E; }
.severity-badge.medium .dot { background: #F59E0B; }
.severity-badge.high { background: #FEF2F2; color: #991B1B; }
.severity-badge.high .dot { background: #EF4444; }
.severity-badge.critical { background: #FEF2F2; color: #7F1D1D; border: 1px solid #DC2626; }
.severity-badge.critical .dot { background: #DC2626; }

.notes-section {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 12px 16px;
    margin-top: 8px;
}

.notes-section .note-item {
    padding: 6px 0;
    border-bottom: 1px solid var(--gray-200);
    font-size: 0.78rem;
}

.notes-section .note-item:last-child {
    border-bottom: none;
}

.notes-section .note-item .timestamp {
    font-size: 0.6rem;
    color: var(--gray-400);
}

.notes-section .note-item .note-content {
    color: var(--gray-700);
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.action-buttons a {
    padding: 8px 20px;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
}

.action-buttons .btn-update {
    background: #EFF6FF;
    color: #3B82F6;
}

.action-buttons .btn-update:hover {
    background: #DBEAFE;
}

.action-buttons .btn-resolve {
    background: #ECFDF5;
    color: #10B981;
}

.action-buttons .btn-resolve:hover {
    background: #D1FAE5;
}

.action-buttons .btn-escalate {
    background: #FEF2F2;
    color: #DC2626;
}

.action-buttons .btn-escalate:hover {
    background: #FEE2E2;
}

.action-buttons .btn-close {
    background: var(--gray-100);
    color: var(--gray-500);
}

.action-buttons .btn-close:hover {
    background: var(--gray-200);
}

.action-buttons .btn-back {
    background: var(--gray-100);
    color: var(--gray-700);
}

.action-buttons .btn-back:hover {
    background: var(--gray-200);
}

.photos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
    margin-top: 8px;
}

.photos-grid .photo-item {
    background: var(--gray-50);
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--gray-200);
}

.photos-grid .photo-item img {
    width: 100%;
    height: 120px;
    object-fit: cover;
}

.photos-grid .photo-item .photo-label {
    font-size: 0.55rem;
    padding: 4px 8px;
    color: var(--gray-500);
    text-align: center;
}

@media (max-width: 768px) {
    .incident-card {
        padding: 16px 18px;
    }
    .detail-grid {
        grid-template-columns: 1fr;
    }
    .incident-header {
        flex-direction: column;
    }
    .action-buttons {
        flex-direction: column;
    }
    .action-buttons a {
        width: 100%;
        text-align: center;
    }
    .photos-grid {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="incident-container">
            <!-- Incident Header -->
            <div class="incident-header">
                <div>
                    <h1 class="title">
                        <i class="fas fa-exclamation-triangle" style="color:#EF4444;"></i>
                        #<?php echo $incident_id; ?> - <?php echo htmlspecialchars($incident['title']); ?>
                        <?php if ($incident['is_panic'] == 1): ?>
                            <span class="panic-badge"><i class="fas fa-exclamation-circle"></i> PANIC</span>
                        <?php endif; ?>
                    </h1>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px;">
                        <span class="status-badge <?php echo $incident['status']; ?>">
                            <span class="dot"></span>
                            <?php echo $status_labels[$incident['status']] ?? ucfirst(str_replace('_', ' ', $incident['status'])); ?>
                        </span>
                        <span class="severity-badge <?php echo $incident['severity']; ?>">
                            <span class="dot"></span>
                            <?php echo $severity_labels[$incident['severity']] ?? ucfirst($incident['severity']); ?>
                        </span>
                        <span style="font-size:0.7rem;color:var(--gray-500);">
                            <i class="fas fa-tag"></i> <?php echo $incident_types[$incident['incident_type']] ?? ucfirst($incident['incident_type']); ?>
                        </span>
                    </div>
                </div>
                <div style="font-size:0.75rem;color:var(--gray-400);">
                    Reported: <?php echo date('M j, Y g:i A', strtotime($incident['created_at'])); ?>
                </div>
            </div>

            <!-- Incident Details -->
            <div class="incident-card">
                <div class="card-title"><i class="fas fa-info-circle"></i> Incident Details</div>
                
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="label">Location</span>
                        <span class="value">
                            <?php 
                                $location = $incident['pu_name'] ?? $incident['ward_name'] ?? $incident['lga_name'] ?? 'N/A';
                                if ($incident['pu_name']) {
                                    echo htmlspecialchars($incident['pu_name']) . ' (' . htmlspecialchars($incident['pu_code'] ?? '') . ')';
                                } else {
                                    echo htmlspecialchars($location);
                                }
                            ?>
                            <?php if ($incident['lga_name']): ?>
                                <br /><span style="font-size:0.7rem;color:var(--gray-500);"><?php echo htmlspecialchars($incident['lga_name']); ?> LGA</span>
                            <?php endif; ?>
                            <?php if ($incident['state_name']): ?>
                                <br /><span style="font-size:0.7rem;color:var(--gray-500);"><?php echo htmlspecialchars($incident['state_name']); ?> State</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Election</span>
                        <span class="value">
                            <?php echo htmlspecialchars($incident['election_name'] ?? 'N/A'); ?>
                            <?php if ($incident['election_type']): ?>
                                <br /><span style="font-size:0.7rem;color:var(--gray-500);"><?php echo ucfirst($incident['election_type']); ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Reported By</span>
                        <span class="value">
                            <?php echo htmlspecialchars($incident['reporter_first_name'] ?? '') . ' ' . htmlspecialchars($incident['reporter_last_name'] ?? 'Unknown'); ?>
                            <?php if ($incident['reporter_email']): ?>
                                <br /><span style="font-size:0.7rem;color:var(--gray-500);"><?php echo htmlspecialchars($incident['reporter_email']); ?></span>
                            <?php endif; ?>
                            <?php if ($incident['reporter_phone']): ?>
                                <br /><span style="font-size:0.7rem;color:var(--gray-500);"><?php echo htmlspecialchars($incident['reporter_phone']); ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Assigned To</span>
                        <span class="value">
                            <?php if ($incident['assigned_to']): ?>
                                <?php echo htmlspecialchars($incident['assigned_first_name'] ?? '') . ' ' . htmlspecialchars($incident['assigned_last_name'] ?? ''); ?>
                                <?php if ($incident['assigned_email']): ?>
                                    <br /><span style="font-size:0.7rem;color:var(--gray-500);"><?php echo htmlspecialchars($incident['assigned_email']); ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:var(--gray-400);">Unassigned</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($incident['resolved_by']): ?>
                        <div class="detail-item">
                            <span class="label">Resolved By</span>
                            <span class="value">
                                <?php echo htmlspecialchars($incident['resolved_first_name'] ?? '') . ' ' . htmlspecialchars($incident['resolved_last_name'] ?? ''); ?>
                                <?php if ($incident['resolved_at']): ?>
                                    <br /><span style="font-size:0.7rem;color:var(--gray-500);"><?php echo date('M j, Y g:i A', strtotime($incident['resolved_at'])); ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <?php if ($incident['gps_lat'] && $incident['gps_lng']): ?>
                        <div class="detail-item">
                            <span class="label">GPS Coordinates</span>
                            <span class="value">
                                <?php echo number_format($incident['gps_lat'], 6); ?>, <?php echo number_format($incident['gps_lng'], 6); ?>
                                <?php if ($incident['gps_accuracy']): ?>
                                    <br /><span style="font-size:0.7rem;color:var(--gray-500);">Accuracy: ±<?php echo $incident['gps_accuracy']; ?>m</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Description -->
            <div class="incident-card">
                <div class="card-title"><i class="fas fa-file-alt"></i> Description</div>
                <p style="font-size:0.9rem;color:var(--gray-700);white-space:pre-wrap;margin:0;">
                    <?php echo htmlspecialchars($incident['description']); ?>
                </p>
            </div>

            <!-- Notes -->
            <?php if (!empty($incident['resolution_notes'])): ?>
                <div class="incident-card">
                    <div class="card-title"><i class="fas fa-sticky-note"></i> Notes & Updates</div>
                    <div class="notes-section">
                        <?php 
                            $notes_lines = explode("\n", $incident['resolution_notes']);
                            foreach ($notes_lines as $line):
                                if (empty(trim($line))) continue;
                                if (preg_match('/^\[([^\]]+)\]\s*(.+)/', $line, $matches)):
                        ?>
                            <div class="note-item">
                                <div class="timestamp"><?php echo $matches[1]; ?></div>
                                <div class="note-content"><?php echo htmlspecialchars($matches[2]); ?></div>
                            </div>
                        <?php else: ?>
                            <div class="note-item">
                                <div class="note-content"><?php echo htmlspecialchars($line); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Photos -->
            <?php if (!empty($incident['photo_urls_json'])): 
                $photos = json_decode($incident['photo_urls_json'], true);
            ?>
                <?php if (!empty($photos)): ?>
                    <div class="incident-card">
                        <div class="card-title"><i class="fas fa-image"></i> Photos</div>
                        <div class="photos-grid">
                            <?php foreach ($photos as $photo): ?>
                                <div class="photo-item">
                                    <img src="<?php echo htmlspecialchars($photo); ?>" alt="Incident Photo" />
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Actions -->
            <div class="incident-card">
                <div class="card-title"><i class="fas fa-tasks"></i> Actions</div>
                <div class="action-buttons">
                    <?php if ($incident['status'] === 'reported' || $incident['status'] === 'acknowledged'): ?>
                        <a href="incident-update.php?id=<?php echo $incident_id; ?>" class="btn-update">
                            <i class="fas fa-edit"></i> Update Status
                        </a>
                        <a href="incident-escalate.php?id=<?php echo $incident_id; ?>" class="btn-escalate">
                            <i class="fas fa-arrow-up"></i> Escalate
                        </a>
                    <?php endif; ?>
                    <?php if ($incident['status'] === 'investigating'): ?>
                        <a href="incident-resolve.php?id=<?php echo $incident_id; ?>" class="btn-resolve">
                            <i class="fas fa-check"></i> Resolve
                        </a>
                        <a href="incident-escalate.php?id=<?php echo $incident_id; ?>" class="btn-escalate">
                            <i class="fas fa-arrow-up"></i> Escalate
                        </a>
                    <?php endif; ?>
                    <?php if ($incident['status'] === 'resolved'): ?>
                        <a href="incident-close.php?id=<?php echo $incident_id; ?>" class="btn-close">
                            <i class="fas fa-times"></i> Close
                        </a>
                    <?php endif; ?>
                    <a href="incident-add-notes.php?id=<?php echo $incident_id; ?>" class="btn-update">
                        <i class="fas fa-sticky-note"></i> Add Note
                    </a>
                    <a href="incidents.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Incidents
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
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