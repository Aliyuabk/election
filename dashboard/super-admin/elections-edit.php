<?php
// ============================================================
// ELECTION EDIT - SUPER ADMINISTRATOR (FIXED)
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session and check login
SessionManager::start();

// Redirect if not logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Check role - only super_admin can access this page
if (SessionManager::get('role_level') !== 'super_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// ============================================================
// GET ELECTION ID
// ============================================================
$election_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($election_id <= 0) {
    header('Location: elections.php');
    exit();
}

// ============================================================
// FETCH ELECTION DETAILS
// ============================================================
$election = null;
try {
    $stmt = $db->prepare("
        SELECT e.*, t.name as tenant_name, u.full_name as created_by_name
        FROM elections e
        LEFT JOIN tenants t ON e.tenant_id = t.id
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.id = ? AND e.deleted_at IS NULL
    ");
    $stmt->execute([$election_id]);
    $election = $stmt->fetch();
} catch (Exception $e) {
    // Continue
}

if (!$election) {
    header('Location: elections.php');
    exit();
}

// ============================================================
// FETCH TENANTS AND STATES
// ============================================================
$tenants = [];
try {
    $stmt = $db->query("SELECT id, name FROM tenants WHERE deleted_at IS NULL ORDER BY name");
    $tenants = $stmt->fetchAll();
} catch (Exception $e) {}

$states = [];
try {
    $stmt = $db->query("SELECT id, name FROM states WHERE is_active = 1 ORDER BY name");
    $states = $stmt->fetchAll();
} catch (Exception $e) {}

// Decode JSON data
$selected_states = json_decode($election['states_json'] ?? '[]', true);
$selected_lgas = json_decode($election['lgas_json'] ?? '[]', true);
$selected_wards = json_decode($election['wards_json'] ?? '[]', true);

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_election':
                $name = trim($_POST['name'] ?? '');
                $type = $_POST['type'] ?? 'presidential';
                $cycle = trim($_POST['cycle'] ?? '');
                $election_date = $_POST['election_date'] ?? null;
                $start_time = $_POST['start_time'] ?? null;
                $end_time = $_POST['end_time'] ?? null;
                $status = $_POST['status'] ?? 'draft';
                $description = trim($_POST['description'] ?? '');
                $states_json = isset($_POST['states']) ? json_encode($_POST['states']) : null;
                
                if (empty($name) || empty($type) || empty($cycle) || empty($election_date)) {
                    throw new Exception('Name, type, cycle, and election date are required.');
                }
                
                // Handle time fields - convert empty strings to NULL
                $start_time = !empty($start_time) ? $start_time : null;
                $end_time = !empty($end_time) ? $end_time : null;
                
                $stmt = $db->prepare("
                    UPDATE elections SET
                        name = ?,
                        type = ?,
                        cycle = ?,
                        election_date = ?,
                        start_time = ?,
                        end_time = ?,
                        status = ?,
                        description = ?,
                        states_json = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $name,
                    $type,
                    $cycle,
                    $election_date,
                    $start_time,
                    $end_time,
                    $status,
                    $description,
                    $states_json,
                    $election_id
                ]);
                
                logActivity(
                    SessionManager::get('user_id'),
                    'election_updated',
                    "Updated election: $name (ID: $election_id)"
                );
                
                $success = "Election updated successfully!";
                
                // Refresh election data
                $stmt = $db->prepare("SELECT * FROM elections WHERE id = ?");
                $stmt->execute([$election_id]);
                $election = $stmt->fetch();
                $selected_states = json_decode($election['states_json'] ?? '[]', true);
                break;
                
            case 'delete_election':
                $stmt = $db->prepare("UPDATE elections SET deleted_at = NOW() WHERE id = ?");
                $stmt->execute([$election_id]);
                
                logActivity(
                    SessionManager::get('user_id'),
                    'election_deleted',
                    "Deleted election ID: $election_id"
                );
                
                header('Location: elections.php?deleted=1');
                exit();
                break;
                
            case 'change_status':
                $new_status = $_POST['status'] ?? '';
                if (empty($new_status)) throw new Exception('Status is required.');
                
                $stmt = $db->prepare("UPDATE elections SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_status, $election_id]);
                
                logActivity(
                    SessionManager::get('user_id'),
                    'election_status_changed',
                    "Changed election ID: $election_id status to $new_status"
                );
                
                $success = "Election status updated to " . ucfirst($new_status);
                
                // Refresh election data
                $stmt = $db->prepare("SELECT * FROM elections WHERE id = ?");
                $stmt->execute([$election_id]);
                $election = $stmt->fetch();
                break;
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
        error_log("Election update PDO Error: " . $e->getMessage());
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Election update Error: " . $e->getMessage());
    }
}

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<!-- Rest of HTML remains the same (all the styles and HTML content from your original file) -->
<style>
    /* ============================================================
       ELECTION EDIT - PRO STYLES
       ============================================================ */
    
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
    
    .btn-primary {
        padding: 8px 18px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25);
    }
    .btn-outline {
        padding: 8px 16px;
        background: transparent;
        color: var(--gray-600);
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.82rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-outline:hover {
        background: var(--gray-50);
        border-color: var(--gray-300);
    }
    .btn-danger {
        padding: 8px 18px;
        background: var(--danger);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-danger:hover {
        background: #DC2626;
        transform: translateY(-1px);
    }
    
    .form-container {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 28px 32px;
        box-shadow: var(--shadow);
    }
    .form-container .form-title {
        font-size: 1.1rem;
        font-weight: 700;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .form-container .form-subtitle {
        color: var(--gray-500);
        font-size: 0.85rem;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--gray-100);
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px 24px;
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .form-group.full-width {
        grid-column: 1 / -1;
    }
    .form-group label {
        font-weight: 600;
        font-size: 0.82rem;
        color: var(--gray-700);
    }
    .form-group label .required {
        color: var(--danger);
        margin-left: 2px;
    }
    .form-group .help-text {
        font-size: 0.7rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 10px 14px;
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        transition: var(--transition);
        background: var(--gray-50);
        color: var(--gray-700);
        width: 100%;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
    }
    .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }
    
    select[multiple] {
        height: 120px;
        padding: 8px;
    }
    select[multiple] option {
        padding: 6px 10px;
        border-radius: 4px;
    }
    select[multiple] option:hover {
        background: #EFF6FF;
    }
    
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid var(--gray-200);
        flex-wrap: wrap;
    }
    .form-actions .btn {
        padding: 10px 28px;
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
    .form-actions .btn-primary {
        background: var(--primary);
        color: white;
    }
    .form-actions .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25);
    }
    .form-actions .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .form-actions .btn-secondary:hover {
        background: var(--gray-200);
    }
    
    .status-bar {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 12px 20px;
        margin-bottom: 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        box-shadow: var(--shadow);
    }
    .status-bar .status-info {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .badge-status .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .badge-status.draft { background: var(--gray-100); color: var(--gray-500); }
    .badge-status.draft .dot { background: var(--gray-400); }
    .badge-status.upcoming { background: #FFFBEB; color: #92400E; }
    .badge-status.upcoming .dot { background: #F59E0B; }
    .badge-status.active { background: #ECFDF5; color: #065F46; }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.completed { background: #EFF6FF; color: #1E40AF; }
    .badge-status.completed .dot { background: #3B82F6; }
    .badge-status.cancelled { background: #FEF2F2; color: #991B1B; }
    .badge-status.cancelled .dot { background: #EF4444; }
    
    .error-message {
        background: #FEF2F2;
        color: #DC2626;
        padding: 14px 18px;
        border-radius: 10px;
        font-size: 0.85rem;
        margin-bottom: 16px;
        border: 1px solid #FECACA;
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }
    .success-message {
        background: #ECFDF5;
        color: #065F46;
        padding: 14px 18px;
        border-radius: 10px;
        font-size: 0.85rem;
        margin-bottom: 16px;
        border: 1px solid #A7F3D0;
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }
    
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.4);
        z-index: 300;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .modal-overlay.active { display: flex; }
    .modal {
        background: white;
        border-radius: var(--radius);
        max-width: 440px;
        width: 100%;
        padding: 28px 32px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        animation: modalIn 0.25s ease;
    }
    @keyframes modalIn {
        from { transform: scale(0.95) translateY(10px); opacity: 0; }
        to { transform: scale(1) translateY(0); opacity: 1; }
    }
    .modal .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    .modal .modal-header h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--gray-800);
    }
    .modal .modal-header .close-btn {
        background: none;
        border: none;
        font-size: 1.4rem;
        color: var(--gray-400);
        cursor: pointer;
        transition: var(--transition);
        padding: 0 4px;
    }
    .modal .modal-header .close-btn:hover {
        color: var(--gray-600);
    }
    .modal .modal-body {
        margin-bottom: 20px;
        color: var(--gray-600);
        font-size: 0.9rem;
        line-height: 1.6;
    }
    .modal .modal-body strong {
        color: var(--gray-800);
    }
    .modal .modal-footer {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }
    .modal .modal-footer .btn {
        padding: 8px 20px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .modal .modal-footer .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .modal .modal-footer .btn-secondary:hover {
        background: var(--gray-200);
    }
    .modal .modal-footer .btn-danger {
        background: var(--danger);
        color: white;
    }
    .modal .modal-footer .btn-danger:hover {
        background: #DC2626;
    }
    
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .form-container {
            padding: 20px;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            justify-content: center;
            width: 100%;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .status-bar {
            flex-direction: column;
            align-items: flex-start;
        }
    }
    @media (max-width: 480px) {
        .form-container {
            padding: 16px;
        }
        .form-group input,
        .form-group select {
            padding: 8px 12px;
            font-size: 0.8rem;
        }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-edit" style="color:var(--primary);margin-right:8px;"></i> Edit Election
                    <small>Update election details for <?php echo htmlspecialchars($election['name']); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="elections-view.php?id=<?php echo $election_id; ?>" class="btn-outline">
                    <i class="fas fa-eye"></i> View
                </a>
                <a href="elections.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Error/Success Messages -->
        <?php if (!empty($error)): ?>
            <div class="error-message"><i class="fas fa-exclamation-circle"></i><div><?php echo $error; ?></div></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="success-message"><i class="fas fa-check-circle"></i><div><?php echo $success; ?></div></div>
        <?php endif; ?>

        <!-- Status Bar -->
        <div class="status-bar">
            <div class="status-info">
                <span style="font-weight:500;font-size:0.85rem;">Current Status:</span>
                <span class="badge-status <?php echo $election['status']; ?>">
                    <span class="dot"></span>
                    <?php echo ucfirst($election['status']); ?>
                </span>
                <span style="font-size:0.8rem;color:var(--gray-500);">
                    <i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($election['election_date'])); ?>
                </span>
                <span style="font-size:0.8rem;color:var(--gray-500);">
                    <i class="fas fa-tag"></i> <?php echo ucfirst(str_replace('_', ' ', $election['type'])); ?>
                </span>
            </div>
            <div style="font-size:0.8rem;color:var(--gray-400);">
                Created: <?php echo date('M j, Y', strtotime($election['created_at'])); ?>
                <?php if ($election['created_by_name']): ?>
                    by <?php echo htmlspecialchars($election['created_by_name']); ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="form-container">
            <div class="form-title">
                <i class="fas fa-vote-yea" style="color:var(--primary);"></i> Election Details
            </div>
            <div class="form-subtitle">
                Update the information for <?php echo htmlspecialchars($election['name']); ?>.
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_election">
                <div class="form-grid">
                    <!-- Basic Information -->
                    <div class="form-section-title" style="grid-column:1/-1;font-weight:600;font-size:0.9rem;color:var(--gray-700);padding-top:8px;border-bottom:1px solid var(--gray-100);padding-bottom:8px;margin-bottom:4px;">
                        <i class="fas fa-info-circle" style="color:var(--primary);"></i> Basic Information
                    </div>
                    
                    <div class="form-group">
                        <label>Election Name <span class="required">*</span></label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($election['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Election Type <span class="required">*</span></label>
                        <select name="type" required>
                            <option value="presidential" <?php echo $election['type'] === 'presidential' ? 'selected' : ''; ?>>Presidential</option>
                            <option value="governorship" <?php echo $election['type'] === 'governorship' ? 'selected' : ''; ?>>Governorship</option>
                            <option value="senatorial" <?php echo $election['type'] === 'senatorial' ? 'selected' : ''; ?>>Senatorial</option>
                            <option value="house_of_reps" <?php echo $election['type'] === 'house_of_reps' ? 'selected' : ''; ?>>House of Representatives</option>
                            <option value="house_of_assembly" <?php echo $election['type'] === 'house_of_assembly' ? 'selected' : ''; ?>>House of Assembly</option>
                            <option value="lga_chairman" <?php echo $election['type'] === 'lga_chairman' ? 'selected' : ''; ?>>LGA Chairman</option>
                            <option value="councillorship" <?php echo $election['type'] === 'councillorship' ? 'selected' : ''; ?>>Councillorship</option>
                            <option value="party_primary" <?php echo $election['type'] === 'party_primary' ? 'selected' : ''; ?>>Party Primary</option>
                            <option value="internal_party" <?php echo $election['type'] === 'internal_party' ? 'selected' : ''; ?>>Internal Party</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Election Cycle <span class="required">*</span></label>
                        <input type="text" name="cycle" value="<?php echo htmlspecialchars($election['cycle']); ?>" required>
                        <div class="help-text">The election year or cycle (e.g., 2027, 2023-2027)</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="draft" <?php echo $election['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="upcoming" <?php echo $election['status'] === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                            <option value="active" <?php echo $election['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="completed" <?php echo $election['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $election['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="archived" <?php echo $election['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                        </select>
                    </div>

                    <!-- Date & Time -->
                    <div class="form-section-title" style="grid-column:1/-1;font-weight:600;font-size:0.9rem;color:var(--gray-700);padding-top:8px;border-bottom:1px solid var(--gray-100);padding-bottom:8px;margin-bottom:4px;">
                        <i class="fas fa-calendar-alt" style="color:var(--primary);"></i> Date &amp; Time
                    </div>
                    
                    <div class="form-group">
                        <label>Election Date <span class="required">*</span></label>
                        <input type="date" name="election_date" value="<?php echo htmlspecialchars($election['election_date']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="start_time" value="<?php echo htmlspecialchars($election['start_time'] ?? ''); ?>">
                        <div class="help-text">When voting begins (optional)</div>
                    </div>
                    
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="end_time" value="<?php echo htmlspecialchars($election['end_time'] ?? ''); ?>">
                        <div class="help-text">When voting ends (optional)</div>
                    </div>

                    <!-- Description -->
                    <div class="form-section-title" style="grid-column:1/-1;font-weight:600;font-size:0.9rem;color:var(--gray-700);padding-top:8px;border-bottom:1px solid var(--gray-100);padding-bottom:8px;margin-bottom:4px;">
                        <i class="fas fa-align-left" style="color:var(--primary);"></i> Additional Information
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea name="description" placeholder="Enter election description, notes, or details..."><?php echo htmlspecialchars($election['description'] ?? ''); ?></textarea>
                        <div class="help-text">Optional description or notes about the election.</div>
                    </div>

                    <!-- Jurisdiction -->
                    <div class="form-section-title" style="grid-column:1/-1;font-weight:600;font-size:0.9rem;color:var(--gray-700);padding-top:8px;border-bottom:1px solid var(--gray-100);padding-bottom:8px;margin-bottom:4px;">
                        <i class="fas fa-map-marked-alt" style="color:var(--primary);"></i> Jurisdiction
                    </div>
                    
                    <div class="form-group full-width">
                        <label>States</label>
                        <select name="states[]" multiple>
                            <?php foreach ($states as $state): ?>
                                <option value="<?php echo $state['id']; ?>" <?php echo in_array($state['id'], $selected_states) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($state['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i> 
                            Hold <kbd>Ctrl</kbd> (Windows) or <kbd>Cmd</kbd> (Mac) to select multiple states. Leave empty for all states.
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Election</button>
                    <a href="elections-view.php?id=<?php echo $election_id; ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                    <?php if ($election['status'] !== 'cancelled' && $election['status'] !== 'completed'): ?>
                        <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</main>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Delete Election</h3>
            <button class="close-btn" onclick="closeModal('deleteModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($election['name']); ?></strong>?</p>
            <p style="color:var(--danger);font-size:0.85rem;margin-top:8px;">
                <i class="fas fa-exclamation-triangle"></i> This action cannot be undone. All associated data will be removed.
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_election">
                <button type="submit" class="btn btn-danger">Delete Election</button>
            </form>
        </div>
    </div>
</div>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
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
// MODAL FUNCTIONS
// ============================================================
function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

function confirmDelete() {
    openModal('deleteModal');
}

// ============================================================
// SEARCH
// ============================================================
var searchInput = document.getElementById('searchInput');
var searchResults = document.getElementById('searchResults');
var searchTimeout;

if (searchInput) {
    searchInput.addEventListener('input', function() {
        var query = this.value.trim();
        clearTimeout(searchTimeout);
        if (query.length < 2) {
            if (searchResults) searchResults.classList.remove('active');
            return;
        }
        searchTimeout = setTimeout(function() {
            fetch('search.php?q=' + encodeURIComponent(query))
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (searchResults) {
                        searchResults.innerHTML = '';
                        if (data && data.length > 0) {
                            data.forEach(function(item) {
                                var div = document.createElement('a');
                                div.className = 'result-item';
                                div.href = item.url || '#';
                                div.innerHTML = '<i class="fas ' + (item.icon || 'fa-file') + '"></i><span class="text-truncate">' + (item.label || item.name || '') + '</span><span class="result-type">' + ((item.type || '').charAt(0).toUpperCase() + (item.type || '').slice(1)) + '</span>';
                                searchResults.appendChild(div);
                            });
                            searchResults.classList.add('active');
                        } else {
                            searchResults.innerHTML = '<div style="padding:12px;text-align:center;color:var(--gray-500);font-size:0.8rem;"><i class="fas fa-search" style="display:block;font-size:1.2rem;margin-bottom:4px;"></i>No results found</div>';
                            searchResults.classList.add('active');
                        }
                    }
                })
                .catch(function() {});
        }, 300);
    });

    document.addEventListener('click', function(e) {
        var wrapper = document.querySelector('.search-wrapper');
        if (wrapper && !wrapper.contains(e.target) && searchResults) {
            searchResults.classList.remove('active');
        }
    });
}
</script>
</body>
</html>