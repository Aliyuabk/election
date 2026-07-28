<?php
// ============================================================
// SENATORIAL COORDINATOR - INCIDENT DETAILS
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

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// GET INCIDENT ID
// ============================================================
$incident_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$incident_id) {
    header('Location: incidents.php');
    exit();
}

// ============================================================
// GET INCIDENT DATA
// ============================================================
$incident = null;
try {
    $stmt = $db->prepare("
        SELECT 
            i.*,
            u.full_name as reporter_name,
            u.email as reporter_email,
            u.phone as reporter_phone,
            pu.name as pu_name,
            pu.code as pu_code,
            w.name as ward_name,
            l.name as lga_name,
            s.name as state_name,
            v.full_name as assigned_to_name,
            r.full_name as resolved_by_name
        FROM incidents i
        LEFT JOIN users u ON i.reporter_id = u.id
        LEFT JOIN polling_units pu ON i.pu_id = pu.id
        LEFT JOIN wards w ON i.ward_id = w.id
        LEFT JOIN lgas l ON i.lga_id = l.id
        LEFT JOIN states s ON i.state_id = s.id
        LEFT JOIN users v ON i.assigned_to = v.id
        LEFT JOIN users r ON i.resolved_by = r.id
        WHERE i.id = ? AND i.tenant_id = ?
    ");
    $stmt->execute([$incident_id, $tenant_id]);
    $incident = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching incident: " . $e->getMessage());
}

if (!$incident) {
    header('Location: incidents.php');
    exit();
}

// ============================================================
// HANDLE STATUS UPDATE
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? '';
    $resolution_notes = trim($_POST['resolution_notes'] ?? '');
    
    if (empty($status)) {
        $error = 'Please select a status.';
    } else {
        try {
            $resolved_at = $status === 'resolved' ? date('Y-m-d H:i:s') : null;
            $resolved_by = $status === 'resolved' ? $user_id : null;
            
            $stmt = $db->prepare("
                UPDATE incidents SET 
                    status = ?,
                    resolved_at = ?,
                    resolved_by = ?,
                    resolution_notes = ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$status, $resolved_at, $resolved_by, $resolution_notes, $incident_id, $tenant_id]);
            
            logActivity($user_id, 'incident_updated', "Updated incident: {$incident['title']} status to $status (ID: $incident_id)");
            
            $success = 'Incident updated successfully!';
            
            // Refresh incident data
            $stmt = $db->prepare("SELECT * FROM incidents WHERE id = ?");
            $stmt->execute([$incident_id]);
            $incident = $stmt->fetch();
            
        } catch (Exception $e) {
            $error = 'Error updating incident: ' . $e->getMessage();
            error_log("Incident update error: " . $e->getMessage());
        }
    }
}

$page_title = 'Incident Details';
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
}
.page-header h2 small {
    font-size: 0.8rem;
    font-weight: 400;
    color: var(--gray-500);
    display: block;
    margin-top: 2px;
}

.detail-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
}
.detail-card .incident-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--gray-200);
}
.detail-card .incident-header .title {
    font-size: 1.2rem;
    font-weight: 700;
}
.detail-card .incident-header .badge-panic {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    background: #7F1D1D;
    color: white;
}

.status-badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}
.status-badge.reported { background: #FEE2E2; color: #DC2626; }
.status-badge.investigating { background: #FEF3C7; color: #D97706; }
.status-badge.resolved { background: #D1FAE5; color: #059669; }
.status-badge.escalated { background: #FEE2E2; color: #DC2626; }

.severity-badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}
.severity-badge.critical { background: #7F1D1D; color: white; }
.severity-badge.high { background: #DC2626; color: white; }
.severity-badge.medium { background: #F59E0B; color: white; }
.severity-badge.low { background: #10B981; color: white; }

.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin: 16px 0;
}
.detail-grid .detail-item {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 12px 16px;
}
.detail-grid .detail-item .label {
    font-size: 0.7rem;
    color: var(--gray-400);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.detail-grid .detail-item .value {
    font-weight: 600;
    font-size: 0.9rem;
}

.description-box {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 16px;
    margin: 16px 0;
}
.description-box .label {
    font-size: 0.7rem;
    color: var(--gray-400);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}
.description-box .description {
    color: var(--gray-700);
    white-space: pre-wrap;
}

.update-form {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--gray-200);
}
.update-form .form-group {
    margin-bottom: 12px;
}
.update-form .form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.82rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.update-form .form-group select,
.update-form .form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    transition: var(--transition);
    background: var(--gray-50);
    color: var(--gray-700);
}
.update-form .form-group select:focus,
.update-form .form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}
.update-form .form-group textarea {
    min-height: 80px;
    resize: vertical;
}

.btn {
    padding: 8px 20px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-primary {
    background: var(--primary);
    color: white;
}
.btn-primary:hover {
    background: var(--primary-dark);
}
.btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.btn-secondary:hover {
    background: var(--gray-200);
}
.btn-success {
    background: #10B981;
    color: white;
}
.btn-success:hover {
    background: #059669;
}

.alert {
    padding: 14px 18px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    border: 1px solid transparent;
}
.alert i {
    margin-top: 2px;
    font-size: 1.1rem;
}
.alert-success {
    background: #ECFDF5;
    color: #065F46;
    border-color: #A7F3D0;
}
.alert-error {
    background: #FEF2F2;
    color: #DC2626;
    border-color: #FECACA;
}

.btn-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .detail-grid {
        grid-template-columns: 1fr;
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
                    <i class="fas fa-info-circle" style="color:var(--primary);margin-right:8px;"></i> 
                    Incident Details
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="incidents.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Incidents
                </a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $success; ?></div>
            </div>
        <?php endif; ?>

        <div class="detail-card">
            <!-- Header -->
            <div class="incident-header">
                <div>
                    <div class="title">
                        <?php echo htmlspecialchars($incident['title']); ?>
                        <?php if ($incident['is_panic']): ?>
                            <span class="badge-panic"><i class="fas fa-exclamation-triangle"></i> PANIC</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:0.85rem;color:var(--gray-500);margin-top:4px;">
                        <span class="status-badge <?php echo $incident['status']; ?>">
                            <?php echo ucfirst($incident['status']); ?>
                        </span>
                        <span class="severity-badge <?php echo $incident['severity']; ?>" style="margin-left:8px;">
                            <?php echo ucfirst($incident['severity']); ?>
                        </span>
                        <span style="margin-left:8px;">
                            <span class="incident-type-tag" style="padding:2px 10px;border-radius:20px;font-size:0.7rem;background:var(--gray-100);">
                                <?php echo ucfirst(str_replace('_', ' ', $incident['incident_type'])); ?>
                            </span>
                        </span>
                    </div>
                </div>
                <div style="font-size:0.8rem;color:var(--gray-400);">
                    <i class="fas fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($incident['created_at'])); ?>
                </div>
            </div>

            <!-- Details -->
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="label">Reported By</div>
                    <div class="value"><?php echo htmlspecialchars($incident['reporter_name'] ?? 'Unknown'); ?></div>
                    <?php if ($incident['reporter_email']): ?>
                        <div style="font-size:0.7rem;color:var(--gray-400);">
                            <?php echo htmlspecialchars($incident['reporter_email']); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="detail-item">
                    <div class="label">Location</div>
                    <div class="value">
                        <?php 
                        $location = [];
                        if ($incident['lga_name']) $location[] = $incident['lga_name'];
                        if ($incident['ward_name']) $location[] = $incident['ward_name'];
                        if ($incident['pu_name']) $location[] = $incident['pu_name'];
                        echo htmlspecialchars(implode(' → ', $location) ?: 'Not specified');
                        ?>
                    </div>
                    <?php if ($incident['pu_code']): ?>
                        <div style="font-size:0.7rem;color:var(--gray-400);">
                            PU: <?php echo htmlspecialchars($incident['pu_code']); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="detail-item">
                    <div class="label">Assigned To</div>
                    <div class="value"><?php echo htmlspecialchars($incident['assigned_to_name'] ?? 'Unassigned'); ?></div>
                </div>
                <div class="detail-item">
                    <div class="label">Resolved By</div>
                    <div class="value"><?php echo htmlspecialchars($incident['resolved_by_name'] ?? '—'); ?></div>
                    <?php if ($incident['resolved_at']): ?>
                        <div style="font-size:0.7rem;color:var(--gray-400);">
                            <?php echo date('M j, Y g:i A', strtotime($incident['resolved_at'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($incident['gps_lat'] && $incident['gps_lng']): ?>
                    <div class="detail-item" style="grid-column:1/-1;">
                        <div class="label">GPS Location</div>
                        <div class="value">
                            <a href="https://maps.google.com/?q=<?php echo $incident['gps_lat']; ?>,<?php echo $incident['gps_lng']; ?>" target="_blank" style="color:var(--primary);text-decoration:none;">
                                <i class="fas fa-map-pin"></i> 
                                <?php echo $incident['gps_lat']; ?>, <?php echo $incident['gps_lng']; ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <div class="description-box">
                <div class="label">Description</div>
                <div class="description"><?php echo nl2br(htmlspecialchars($incident['description'])); ?></div>
            </div>

            <!-- Resolution Notes -->
            <?php if ($incident['resolution_notes']): ?>
                <div class="description-box" style="border-left:3px solid #10B981;">
                    <div class="label">Resolution Notes</div>
                    <div class="description"><?php echo nl2br(htmlspecialchars($incident['resolution_notes'])); ?></div>
                </div>
            <?php endif; ?>

            <!-- Update Status Form -->
            <?php if ($incident['status'] !== 'resolved'): ?>
                <div class="update-form">
                    <h4 style="font-size:0.95rem;font-weight:600;margin-bottom:12px;">
                        <i class="fas fa-edit"></i> Update Status
                    </h4>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select name="status" id="status" required>
                                <option value="">Select Status...</option>
                                <option value="reported" <?php echo $incident['status'] === 'reported' ? 'selected' : ''; ?>>Reported</option>
                                <option value="investigating" <?php echo $incident['status'] === 'investigating' ? 'selected' : ''; ?>>Investigating</option>
                                <option value="resolved" <?php echo $incident['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="escalated" <?php echo $incident['status'] === 'escalated' ? 'selected' : ''; ?>>Escalated</option>
                            </select>
                        </div>
                        <div class="form-group" id="resolutionNotesGroup">
                            <label for="resolution_notes">Resolution Notes</label>
                            <textarea name="resolution_notes" id="resolution_notes" placeholder="Add resolution notes..."><?php echo htmlspecialchars($incident['resolution_notes'] ?? ''); ?></textarea>
                        </div>
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Status
                            </button>
                            <a href="incident-escalate.php?id=<?php echo $incident_id; ?>" class="btn" style="background:#DC2626;color:white;">
                                <i class="fas fa-arrow-up"></i> Escalate
                            </a>
                            <a href="incident-close.php?id=<?php echo $incident_id; ?>" class="btn btn-success">
                                <i class="fas fa-check-circle"></i> Resolve
                            </a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// ============================================================
// TOGGLE RESOLUTION NOTES
// ============================================================
document.getElementById('status').addEventListener('change', function() {
    var notesGroup = document.getElementById('resolutionNotesGroup');
    if (this.value === 'resolved') {
        notesGroup.style.display = 'block';
    } else {
        notesGroup.style.display = 'none';
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