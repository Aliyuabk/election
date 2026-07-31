<?php
// ============================================================
// FEDERAL CONSTITUENCY COORDINATOR - INCIDENT DETAILS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'federal_constituency') {
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
// GET INCIDENT HISTORY
// ============================================================
$history = [];
try {
    $stmt = $db->prepare("
        SELECT 
            a.*,
            u.full_name as user_name
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.entity_type = 'incident' AND a.entity_id = ?
        ORDER BY a.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$incident_id]);
    $history = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching incident history: " . $e->getMessage());
}

// ============================================================
// HANDLE STATUS UPDATE
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $resolution_notes = trim($_POST['resolution_notes'] ?? '');
    
    try {
        if ($action === 'resolve') {
            $stmt = $db->prepare("
                UPDATE incidents SET 
                    status = 'resolved',
                    resolved_at = NOW(),
                    resolved_by = ?,
                    resolution_notes = ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$user_id, $resolution_notes, $incident_id, $tenant_id]);
            $success = 'Incident resolved successfully!';
            logActivity($user_id, 'incident_resolved', "Resolved incident: {$incident['title']} (ID: $incident_id)", 'incident', $incident_id);
        } elseif ($action === 'escalate') {
            $stmt = $db->prepare("
                UPDATE incidents SET 
                    status = 'escalated',
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$incident_id, $tenant_id]);
            $success = 'Incident escalated successfully!';
            logActivity($user_id, 'incident_escalated', "Escalated incident: {$incident['title']} (ID: $incident_id)", 'incident', $incident_id);
        } elseif ($action === 'update_status') {
            $status = $_POST['status'] ?? '';
            if (empty($status)) {
                $error = 'Please select a status.';
            } else {
                $stmt = $db->prepare("
                    UPDATE incidents SET 
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([$status, $incident_id, $tenant_id]);
                $success = 'Incident status updated successfully!';
                logActivity($user_id, 'incident_updated', "Updated incident: {$incident['title']} status to $status (ID: $incident_id)", 'incident', $incident_id);
            }
        } elseif ($action === 'add_note') {
            $note = trim($_POST['note'] ?? '');
            if (!empty($note)) {
                $current_notes = $incident['resolution_notes'] ?? '';
                $new_notes = $current_notes . "\n[" . date('Y-m-d H:i') . "] " . $user_name . ": " . $note;
                $stmt = $db->prepare("
                    UPDATE incidents SET 
                        resolution_notes = ?,
                        updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([$new_notes, $incident_id, $tenant_id]);
                $success = 'Note added successfully!';
                logActivity($user_id, 'incident_note', "Added note to incident: {$incident['title']} (ID: $incident_id)", 'incident', $incident_id);
            } else {
                $error = 'Please enter a note.';
            }
        }
        
        // Refresh incident data
        if (empty($error)) {
            $stmt = $db->prepare("SELECT * FROM incidents WHERE id = ?");
            $stmt->execute([$incident_id]);
            $incident = $stmt->fetch();
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
        error_log("Incident action error: " . $e->getMessage());
    }
}

$page_title = 'Incident Details';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.detail-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px 24px;
    margin-bottom: 20px;
}
.detail-card .incident-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--gray-200);
}
.detail-card .incident-header .title {
    font-size: 1.1rem;
    font-weight: 700;
}
.detail-card .incident-header .badge-panic {
    display: inline-block;
    padding: 2px 12px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 700;
    background: #7F1D1D;
    color: white;
    margin-left: 8px;
}
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
.status-badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}
.status-badge.reported { background: #FEE2E2; color: #DC2626; }
.status-badge.acknowledged { background: #FEF3C7; color: #D97706; }
.status-badge.investigating { background: #FEF3C7; color: #D97706; }
.status-badge.resolved { background: #D1FAE5; color: #059669; }
.status-badge.escalated { background: #FEE2E2; color: #DC2626; }
.status-badge.closed { background: #D1FAE5; color: #059669; }

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

.update-form {
    margin-top: 16px;
    padding-top: 16px;
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
    border-radius: 8px;
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    transition: var(--transition);
}
.update-form .form-group select:focus,
.update-form .form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
}
.update-form .form-group textarea {
    min-height: 80px;
    resize: vertical;
}

.btn {
    padding: 8px 18px;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-primary {
    background: var(--primary);
    color: white;
}
.btn-primary:hover {
    background: var(--primary-dark);
}
.btn-success {
    background: #10B981;
    color: white;
}
.btn-success:hover {
    background: #059669;
}
.btn-danger {
    background: #DC2626;
    color: white;
}
.btn-danger:hover {
    background: #B91C1C;
}
.btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.btn-secondary:hover {
    background: var(--gray-200);
}

.alert {
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success {
    background: #ECFDF5;
    color: #065F46;
    border: 1px solid #A7F3D0;
}
.alert-error {
    background: #FEF2F2;
    color: #DC2626;
    border: 1px solid #FECACA;
}

.history-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--gray-100);
}
.history-item:last-child {
    border-bottom: none;
}
.history-item .history-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    flex-shrink: 0;
}
.history-item .history-icon.update { background: #DBEAFE; color: #2563EB; }
.history-item .history-icon.resolve { background: #D1FAE5; color: #059669; }
.history-item .history-icon.escalate { background: #FEE2E2; color: #DC2626; }
.history-item .history-content {
    flex: 1;
}
.history-item .history-content .action {
    font-weight: 500;
    font-size: 0.85rem;
}
.history-item .history-content .desc {
    font-size: 0.78rem;
    color: var(--gray-500);
}
.history-item .history-content .time {
    font-size: 0.65rem;
    color: var(--gray-400);
}

.btn-group {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
}

@media (max-width: 768px) {
    .detail-grid {
        grid-template-columns: 1fr;
    }
    .btn-group {
        flex-direction: column;
    }
    .btn-group .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:4px;">
            <h2 style="font-size:1.3rem;font-weight:700;margin:0;">
                <i class="fas fa-info-circle" style="color:var(--primary);"></i> Incident Details
            </h2>
            <a href="incidents.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Incidents
            </a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo htmlspecialchars($success); ?></div>
            </div>
        <?php endif; ?>

        <!-- Incident Details -->
        <div class="detail-card">
            <div class="incident-header">
                <div>
                    <div class="title">
                        <?php echo htmlspecialchars($incident['title']); ?>
                        <?php if ($incident['is_panic']): ?>
                            <span class="badge-panic"><i class="fas fa-exclamation-triangle"></i> PANIC</span>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top:6px;">
                        <span class="status-badge <?php echo $incident['status']; ?>">
                            <?php echo ucfirst($incident['status']); ?>
                        </span>
                        <span class="severity-badge <?php echo $incident['severity']; ?>" style="margin-left:8px;">
                            <?php echo ucfirst($incident['severity']); ?>
                        </span>
                        <span style="margin-left:8px;font-size:0.75rem;color:var(--gray-400);">
                            <?php echo ucfirst(str_replace('_', ' ', $incident['incident_type'])); ?>
                        </span>
                    </div>
                </div>
                <div style="font-size:0.8rem;color:var(--gray-400);">
                    <i class="fas fa-clock"></i> <?php echo date('M d, Y g:i A', strtotime($incident['created_at'])); ?>
                </div>
            </div>

            <div class="detail-grid">
                <div class="detail-item">
                    <div class="label">Reported By</div>
                    <div class="value"><?php echo htmlspecialchars($incident['reporter_name'] ?? 'Unknown'); ?></div>
                    <?php if ($incident['reporter_email']): ?>
                        <div style="font-size:0.7rem;color:var(--gray-400);"><?php echo htmlspecialchars($incident['reporter_email']); ?></div>
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
                            <?php echo date('M d, Y g:i A', strtotime($incident['resolved_at'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="description-box">
                <div class="label">Description</div>
                <div class="description"><?php echo nl2br(htmlspecialchars($incident['description'])); ?></div>
            </div>

            <?php if ($incident['resolution_notes']): ?>
                <div class="description-box" style="border-left:3px solid #10B981;">
                    <div class="label">Resolution Notes</div>
                    <div class="description"><?php echo nl2br(htmlspecialchars($incident['resolution_notes'])); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!in_array($incident['status'], ['resolved', 'closed'])): ?>
                <div class="update-form">
                    <h4 style="font-size:0.95rem;font-weight:600;margin-bottom:12px;">
                        <i class="fas fa-edit"></i> Update Incident
                    </h4>
                    
                    <!-- Update Status -->
                    <form method="POST" style="margin-bottom:12px;">
                        <input type="hidden" name="action" value="update_status">
                        <div class="form-group">
                            <label>Change Status</label>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                <select name="status" style="flex:1;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;">
                                    <option value="reported" <?php echo $incident['status'] === 'reported' ? 'selected' : ''; ?>>Reported</option>
                                    <option value="acknowledged" <?php echo $incident['status'] === 'acknowledged' ? 'selected' : ''; ?>>Acknowledged</option>
                                    <option value="investigating" <?php echo $incident['status'] === 'investigating' ? 'selected' : ''; ?>>Investigating</option>
                                    <option value="resolved" <?php echo $incident['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="escalated" <?php echo $incident['status'] === 'escalated' ? 'selected' : ''; ?>>Escalated</option>
                                </select>
                                <button type="submit" class="btn btn-primary">Update Status</button>
                            </div>
                        </div>
                    </form>

                    <!-- Quick Actions -->
                    <div class="btn-group">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="resolve">
                            <button type="submit" class="btn btn-success" onclick="return confirm('Mark this incident as resolved?')">
                                <i class="fas fa-check"></i> Resolve
                            </button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="escalate">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Escalate this incident?')">
                                <i class="fas fa-arrow-up"></i> Escalate
                            </button>
                        </form>
                    </div>

                    <!-- Add Note -->
                    <form method="POST" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--gray-200);">
                        <input type="hidden" name="action" value="add_note">
                        <div class="form-group">
                            <label>Add Note</label>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                <textarea name="note" placeholder="Add a note..." style="flex:1;padding:10px 14px;border:1.5px solid var(--gray-200);border-radius:8px;min-height:60px;resize:vertical;"></textarea>
                                <button type="submit" class="btn btn-secondary" style="align-self:flex-end;">Add Note</button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- History -->
        <?php if (!empty($history)): ?>
            <div class="detail-card">
                <h4 style="font-size:0.95rem;font-weight:600;margin-bottom:16px;">
                    <i class="fas fa-history" style="color:var(--primary);"></i> Activity History
                </h4>
                <?php foreach ($history as $item): ?>
                    <div class="history-item">
                        <div class="history-icon <?php 
                            if (strpos($item['activity_type'] ?? '', 'resolve') !== false) echo 'resolve';
                            elseif (strpos($item['activity_type'] ?? '', 'escalate') !== false) echo 'escalate';
                            else echo 'update';
                        ?>">
                            <i class="fas <?php 
                                if (strpos($item['activity_type'] ?? '', 'resolve') !== false) echo 'fa-check-circle';
                                elseif (strpos($item['activity_type'] ?? '', 'escalate') !== false) echo 'fa-arrow-up';
                                else echo 'fa-edit';
                            ?>"></i>
                        </div>
                        <div class="history-content">
                            <div class="action"><?php echo htmlspecialchars($item['user_name'] ?? 'System'); ?></div>
                            <div class="desc"><?php echo htmlspecialchars($item['description'] ?? ''); ?></div>
                            <div class="time"><?php echo date('M d, Y g:i A', strtotime($item['created_at'])); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Sidebar toggle (standard)
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