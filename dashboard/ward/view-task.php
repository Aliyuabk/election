<?php
// ============================================================
// WARD COORDINATOR - VIEW TASK DETAILS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session
SessionManager::start();

// Check if user is logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Only Ward coordinator can access
$user_role_level = SessionManager::get('role_level');
if ($user_role_level !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

// Get user data from session
$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$ward_id = SessionManager::get('ward_id');
$lga_id = SessionManager::get('lga_id');
$state_id = SessionManager::get('state_id');

// Get database connection
$db = getDB();

// ============================================================
// FIX: Ensure ward_id is properly set
// ============================================================
if (empty($ward_id)) {
    try {
        $stmt = $db->prepare("SELECT ward_id, lga_id, state_id FROM users WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$user_id, $tenant_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && !empty($user['ward_id'])) {
            $ward_id = $user['ward_id'];
            $lga_id = $user['lga_id'] ?? $lga_id;
            $state_id = $user['state_id'] ?? $state_id;
            
            // Update session
            SessionManager::set('ward_id', $ward_id);
            SessionManager::set('lga_id', $lga_id);
            SessionManager::set('state_id', $state_id);
        } else {
            $_SESSION['flash_error'] = 'You have not been assigned to any ward. Please contact your administrator.';
            header('Location: ../client-admin/dashboard.php');
            exit();
        }
    } catch (Exception $e) {
        error_log("Error fetching ward_id: " . $e->getMessage());
        $_SESSION['flash_error'] = 'Database error occurred. Please try again.';
        header('Location: ../client-admin/dashboard.php');
        exit();
    }
}

// ============================================================
// GET TASK ID
// ============================================================
$task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($task_id <= 0) {
    $_SESSION['flash_error'] = 'Invalid task ID.';
    header('Location: volunteer-progress.php');
    exit();
}

// ============================================================
// FETCH TASK DETAILS
// ============================================================
$task = null;
$task_updates = [];
$volunteer_tasks_count = 0;

try {
    // Get task details with volunteer and assigner info
    $stmt = $db->prepare("
        SELECT 
            vt.*,
            u.id as volunteer_id,
            u.full_name as volunteer_name,
            u.user_code as volunteer_code,
            u.phone as volunteer_phone,
            u.email as volunteer_email,
            u.photograph_url as volunteer_photo,
            u.status as volunteer_status,
            pu.id as pu_id,
            pu.name as pu_name,
            pu.code as pu_code,
            pu.registered_voters as pu_voters,
            assigned.id as assigned_by_id,
            assigned.full_name as assigned_by_name,
            assigned.user_code as assigned_by_code,
            lga.name as lga_name,
            state.name as state_name,
            ward.name as ward_name
        FROM volunteer_tasks vt
        JOIN users u ON vt.volunteer_id = u.id
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        LEFT JOIN users assigned ON vt.assigned_by = assigned.id
        LEFT JOIN lgas lga ON u.lga_id = lga.id
        LEFT JOIN states state ON u.state_id = state.id
        LEFT JOIN wards ward ON u.ward_id = ward.id
        WHERE vt.id = ? 
        AND u.tenant_id = ? 
        AND u.ward_id = ?
        AND u.deleted_at IS NULL
    ");
    $stmt->execute([$task_id, $tenant_id, $ward_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        $_SESSION['flash_error'] = 'Task not found or you do not have permission to view it.';
        header('Location: volunteer-progress.php');
        exit();
    }
    
    // Get task update history (if you have a task_updates table)
    // If not, we'll show the task log from activity_logs
    $stmt = $db->prepare("
        SELECT 
            al.*,
            u.full_name as user_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.entity_type = 'volunteer_tasks' 
        AND al.entity_id = ?
        ORDER BY al.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$task_id]);
    $task_updates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total tasks for this volunteer
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM volunteer_tasks
        WHERE volunteer_id = ? AND tenant_id = ? AND ward_id = ?
    ");
    $stmt->execute([$task['volunteer_id'], $tenant_id, $ward_id]);
    $volunteer_tasks_count = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching task: " . $e->getMessage());
    $_SESSION['flash_error'] = 'Error loading task details.';
    header('Location: volunteer-progress.php');
    exit();
}

// ============================================================
// HANDLE TASK STATUS UPDATE
// ============================================================
$success_message = '';
$error_message = '';
$show_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $new_status = isset($_POST['status']) ? $_POST['status'] : '';
    $completion_notes = isset($_POST['completion_notes']) ? trim($_POST['completion_notes']) : '';
    $status_notes = isset($_POST['status_notes']) ? trim($_POST['status_notes']) : '';
    
    // CSRF Protection
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $session_token = SessionManager::get('csrf_token');
    
    if (empty($csrf_token) || $csrf_token !== $session_token) {
        $error_message = 'Security validation failed. Please try again.';
    } elseif (empty($new_status)) {
        $error_message = 'Please select a status.';
    } else {
        try {
            $db->beginTransaction();
            
            $update_fields = "status = ?, updated_at = NOW()";
            $params = [$new_status];
            
            if ($new_status === 'completed') {
                $update_fields .= ", completed_at = NOW(), completion_notes = ?";
                $params[] = $completion_notes;
            } elseif ($new_status === 'in_progress') {
                $update_fields .= ", started_at = COALESCE(started_at, NOW())";
            }
            
            // Add status notes if provided
            if (!empty($status_notes)) {
                $update_fields .= ", notes = CONCAT(COALESCE(notes, ''), '\n[', NOW(), '] ', ?)";
                $params[] = $status_notes;
            }
            
            $stmt = $db->prepare("UPDATE volunteer_tasks SET $update_fields WHERE id = ?");
            $params[] = $task_id;
            $stmt->execute($params);
            
            logActivity($user_id, 'task_status_updated', "Updated task ID: $task_id from '{$task['status']}' to '$new_status'", 'volunteer_tasks', $task_id);
            
            $db->commit();
            $success_message = "Task status updated to: " . ucfirst(str_replace('_', ' ', $new_status));
            $show_success = true;
            
            // Refresh task data
            $stmt = $db->prepare("
                SELECT 
                    vt.*,
                    u.id as volunteer_id,
                    u.full_name as volunteer_name,
                    u.user_code as volunteer_code,
                    u.phone as volunteer_phone,
                    u.email as volunteer_email,
                    u.photograph_url as volunteer_photo,
                    u.status as volunteer_status,
                    pu.id as pu_id,
                    pu.name as pu_name,
                    pu.code as pu_code,
                    pu.registered_voters as pu_voters,
                    assigned.id as assigned_by_id,
                    assigned.full_name as assigned_by_name,
                    assigned.user_code as assigned_by_code,
                    lga.name as lga_name,
                    state.name as state_name,
                    ward.name as ward_name
                FROM volunteer_tasks vt
                JOIN users u ON vt.volunteer_id = u.id
                LEFT JOIN polling_units pu ON u.pu_id = pu.id
                LEFT JOIN users assigned ON vt.assigned_by = assigned.id
                LEFT JOIN lgas lga ON u.lga_id = lga.id
                LEFT JOIN states state ON u.state_id = state.id
                LEFT JOIN wards ward ON u.ward_id = ward.id
                WHERE vt.id = ? AND u.tenant_id = ? AND u.ward_id = ?
            ");
            $stmt->execute([$task_id, $tenant_id, $ward_id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Refresh updates
            $stmt = $db->prepare("
                SELECT 
                    al.*,
                    u.full_name as user_name
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.entity_type = 'volunteer_tasks' 
                AND al.entity_id = ?
                ORDER BY al.created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$task_id]);
            $task_updates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = "Error updating task: " . $e->getMessage();
            error_log("Task update error: " . $e->getMessage());
        }
    }
}

// Generate CSRF token
$csrf_token = bin2hex(random_bytes(32));
SessionManager::set('csrf_token', $csrf_token);

$page_title = 'Task Details';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
:root {
    --primary: #0F4C81;
    --primary-light: #1a6bb5;
    --primary-dark: #0a3a62;
    --success: #10B981;
    --success-light: #ECFDF5;
    --danger: #EF4444;
    --danger-light: #FEF2F2;
    --warning: #F59E0B;
    --warning-light: #FEF3C7;
    --info: #3B82F6;
    --info-light: #EFF6FF;
    --gray-50: #F9FAFB;
    --gray-100: #F3F4F6;
    --gray-200: #E5E7EB;
    --gray-300: #D1D5DB;
    --gray-400: #9CA3AF;
    --gray-500: #6B7280;
    --gray-600: #4B5563;
    --gray-700: #374151;
    --gray-800: #1F2937;
    --gray-900: #111827;
    --radius: 8px;
    --shadow: 0 1px 3px rgba(0,0,0,0.1);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
    --transition: all 0.2s ease;
}

.task-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.task-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.task-header h2 i {
    color: var(--primary);
}
.task-header .subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin: 2px 0 0;
}

.alert {
    padding: 14px 18px;
    border-radius: var(--radius);
    margin-bottom: 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    border: 1px solid transparent;
}
.alert i {
    font-size: 1.1rem;
    margin-top: 2px;
}
.alert .alert-content {
    flex: 1;
}
.alert .alert-title {
    font-weight: 600;
    font-size: 0.9rem;
}
.alert .alert-message {
    font-size: 0.85rem;
    opacity: 0.9;
}
.alert-success {
    background: var(--success-light);
    border-color: #D1FAE5;
    color: #065F46;
}
.alert-danger {
    background: var(--danger-light);
    border-color: #FEE2E2;
    color: #991B1B;
}

.task-details-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}

.task-main {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
    box-shadow: var(--shadow);
}

.task-main .task-title {
    font-size: 1.2rem;
    font-weight: 700;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.task-main .task-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 0.8rem;
    color: var(--gray-500);
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--gray-200);
}

.task-main .task-description {
    font-size: 0.95rem;
    line-height: 1.7;
    color: var(--gray-700);
    margin-bottom: 20px;
    padding: 16px;
    background: var(--gray-50);
    border-radius: var(--radius);
    white-space: pre-wrap;
}

.task-main .task-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-top: 16px;
}
.task-main .task-info-grid .info-item {
    padding: 8px 12px;
    background: var(--gray-50);
    border-radius: var(--radius);
}
.task-main .task-info-grid .info-item .label {
    font-size: 0.65rem;
    color: var(--gray-400);
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
}
.task-main .task-info-grid .info-item .value {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--gray-800);
    margin-top: 2px;
}
.task-main .task-info-grid .info-item .value .tag {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
}

/* Status Badge */
.status-badge {
    font-size: 0.7rem;
    padding: 4px 14px;
    border-radius: 20px;
    font-weight: 600;
}
.status-badge.pending {
    background: var(--warning-light);
    color: #92400E;
}
.status-badge.in_progress {
    background: var(--info-light);
    color: #1E40AF;
}
.status-badge.completed {
    background: var(--success-light);
    color: #065F46;
}
.status-badge.cancelled {
    background: var(--danger-light);
    color: #991B1B;
}

/* Priority Badge */
.priority-badge {
    font-size: 0.65rem;
    padding: 2px 12px;
    border-radius: 12px;
    font-weight: 600;
}
.priority-badge.high {
    background: #FEE2E2;
    color: #991B1B;
}
.priority-badge.normal {
    background: #DBEAFE;
    color: #1E40AF;
}
.priority-badge.low {
    background: #D1FAE5;
    color: #065F46;
}

/* Sidebar */
.task-sidebar {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.task-sidebar .card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 20px;
    box-shadow: var(--shadow);
}

.task-sidebar .card .card-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
}

/* Volunteer Info */
.volunteer-info {
    display: flex;
    align-items: center;
    gap: 12px;
}
.volunteer-info .avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--gray-600);
    flex-shrink: 0;
}
.volunteer-info .avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}
.volunteer-info .info .name {
    font-weight: 600;
    font-size: 0.95rem;
}
.volunteer-info .info .sub {
    font-size: 0.75rem;
    color: var(--gray-500);
}

/* Status Update Form */
.status-form {
    margin-top: 16px;
}
.status-form .form-group {
    margin-bottom: 12px;
}
.status-form .form-group label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--gray-600);
    margin-bottom: 4px;
}
.status-form .form-group select,
.status-form .form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    font-family: inherit;
}
.status-form .form-group select:focus,
.status-form .form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(15, 76, 129, 0.1);
}
.status-form .form-group textarea {
    resize: vertical;
    min-height: 60px;
}
.status-form .form-actions {
    display: flex;
    gap: 8px;
}
.status-form .form-actions .btn {
    padding: 8px 20px;
    border-radius: var(--radius);
    font-weight: 600;
    font-size: 0.8rem;
    border: none;
    cursor: pointer;
    transition: var(--transition);
}
.status-form .form-actions .btn-primary {
    background: var(--primary);
    color: white;
}
.status-form .form-actions .btn-primary:hover {
    background: var(--primary-dark);
}
.status-form .form-actions .btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.status-form .form-actions .btn-secondary:hover {
    background: var(--gray-200);
}

/* Update History */
.update-history {
    max-height: 300px;
    overflow-y: auto;
}
.update-history::-webkit-scrollbar {
    width: 4px;
}
.update-history::-webkit-scrollbar-track {
    background: var(--gray-100);
}
.update-history::-webkit-scrollbar-thumb {
    background: var(--gray-300);
    border-radius: 4px;
}
.update-item {
    display: flex;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid var(--gray-100);
}
.update-item:last-child {
    border-bottom: none;
}
.update-item .update-icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--gray-100);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    flex-shrink: 0;
}
.update-item .update-icon.status-change {
    background: #DBEAFE;
    color: #1E40AF;
}
.update-item .update-icon.task-created {
    background: #D1FAE5;
    color: #065F46;
}
.update-item .update-icon.task-completed {
    background: #D1FAE5;
    color: #065F46;
}
.update-item .update-content {
    flex: 1;
}
.update-item .update-content .action {
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--gray-800);
}
.update-item .update-content .details {
    font-size: 0.7rem;
    color: var(--gray-500);
}
.update-item .update-content .time {
    font-size: 0.6rem;
    color: var(--gray-400);
}

/* Stats Mini Cards */
.stats-mini {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}
.stats-mini .stat {
    text-align: center;
    padding: 8px;
    background: var(--gray-50);
    border-radius: var(--radius);
}
.stats-mini .stat .number {
    font-size: 1rem;
    font-weight: 700;
}
.stats-mini .stat .number.blue { color: #3B82F6; }
.stats-mini .stat .number.green { color: #10B981; }
.stats-mini .stat .number.orange { color: #F59E0B; }
.stats-mini .stat .number.red { color: #EF4444; }
.stats-mini .stat .label {
    font-size: 0.55rem;
    color: var(--gray-400);
    text-transform: uppercase;
}

/* Responsive */
@media (max-width: 992px) {
    .task-details-grid {
        grid-template-columns: 1fr;
    }
    .task-main .task-info-grid {
        grid-template-columns: 1fr 1fr;
    }
}
@media (max-width: 768px) {
    .task-main .task-info-grid {
        grid-template-columns: 1fr;
    }
    .stats-mini {
        grid-template-columns: repeat(2, 1fr);
    }
    .task-header {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="task-header">
            <div>
                <h2><i class="fas fa-tasks"></i> Task Details</h2>
                <p class="subtitle">
                    <i class="fas fa-map-marker-alt" style="color:var(--gray-400);"></i> 
                    <?php echo htmlspecialchars($task['ward_name'] ?? 'Ward'); ?> Ward
                    <?php if ($task_id): ?>
                        • Task #<?php echo htmlspecialchars($task_id); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="volunteer-progress.php" class="btn-secondary-sm" style="padding:8px 16px;border:1px solid var(--gray-200);border-radius:var(--radius);color:var(--gray-600);text-decoration:none;font-size:0.8rem;transition:var(--transition);">
                    <i class="fas fa-arrow-left"></i> Back to Progress
                </a>
                <a href="assign-tasks.php" class="btn-primary-sm" style="padding:8px 16px;background:var(--primary);color:white;border-radius:var(--radius);text-decoration:none;font-size:0.8rem;font-weight:600;display:inline-flex;align-items:center;gap:6px;transition:var(--transition);">
                    <i class="fas fa-plus"></i> New Task
                </a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (!empty($success_message) && $show_success): ?>
            <div class="alert alert-success" id="successAlert">
                <i class="fas fa-check-circle"></i>
                <div class="alert-content">
                    <div class="alert-title">Success!</div>
                    <div class="alert-message"><?php echo htmlspecialchars($success_message); ?></div>
                </div>
                <button type="button" onclick="this.parentElement.style.display='none'" style="background:none;border:none;color:inherit;cursor:pointer;font-size:1.2rem;opacity:0.7;">&times;</button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" id="errorAlert">
                <i class="fas fa-exclamation-circle"></i>
                <div class="alert-content">
                    <div class="alert-title">Error</div>
                    <div class="alert-message"><?php echo htmlspecialchars($error_message); ?></div>
                </div>
                <button type="button" onclick="this.parentElement.style.display='none'" style="background:none;border:none;color:inherit;cursor:pointer;font-size:1.2rem;opacity:0.7;">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Task Details Grid -->
        <div class="task-details-grid">
            <!-- Main Content -->
            <div class="task-main">
                <!-- Task Title -->
                <div class="task-title">
                    <?php echo htmlspecialchars($task['title']); ?>
                    <span class="status-badge <?php echo $task['status']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                    </span>
                    <?php if (isset($task['priority']) && $task['priority'] === 'high'): ?>
                        <span class="priority-badge high"><i class="fas fa-exclamation-circle"></i> High Priority</span>
                    <?php elseif (isset($task['priority']) && $task['priority'] === 'normal'): ?>
                        <span class="priority-badge normal">Normal Priority</span>
                    <?php elseif (isset($task['priority']) && $task['priority'] === 'low'): ?>
                        <span class="priority-badge low">Low Priority</span>
                    <?php endif; ?>
                </div>

                <!-- Task Meta -->
                <div class="task-meta">
                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($task['volunteer_name']); ?></span>
                    <span><i class="fas fa-user-check"></i> Assigned by <?php echo htmlspecialchars($task['assigned_by_name'] ?? 'System'); ?></span>
                    <?php if (!empty($task['location'])): ?>
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($task['location']); ?></span>
                    <?php endif; ?>
                    <?php if ($task['due_date']): ?>
                        <span><i class="fas fa-clock"></i> Due: <?php echo date('M d, Y H:i', strtotime($task['due_date'])); ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-calendar"></i> Created: <?php echo date('M d, Y H:i', strtotime($task['created_at'])); ?></span>
                    <?php if ($task['completed_at']): ?>
                        <span style="color:var(--success);"><i class="fas fa-check-circle"></i> Completed: <?php echo date('M d, Y H:i', strtotime($task['completed_at'])); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Task Description -->
                <div class="task-description">
                    <?php echo nl2br(htmlspecialchars($task['description'] ?? 'No description provided.')); ?>
                </div>

                <!-- Task Info Grid -->
                <div class="task-info-grid">
                    <div class="info-item">
                        <div class="label">Task Type</div>
                        <div class="value"><?php echo ucfirst(str_replace('_', ' ', $task['task_type'] ?? 'General')); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Status</div>
                        <div class="value">
                            <span class="status-badge <?php echo $task['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                            </span>
                        </div>
                    </div>
                    <?php if (!empty($task['pu_name'])): ?>
                        <div class="info-item">
                            <div class="label">Polling Unit</div>
                            <div class="value"><?php echo htmlspecialchars($task['pu_name']); ?> (<?php echo htmlspecialchars($task['pu_code']); ?>)</div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($task['lga_name'])): ?>
                        <div class="info-item">
                            <div class="label">LGA / State</div>
                            <div class="value"><?php echo htmlspecialchars($task['lga_name']); ?>, <?php echo htmlspecialchars($task['state_name'] ?? ''); ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($task['completion_notes'])): ?>
                        <div class="info-item" style="grid-column: 1 / -1;">
                            <div class="label">Completion Notes</div>
                            <div class="value" style="font-weight:400;color:var(--gray-600);">
                                <?php echo nl2br(htmlspecialchars($task['completion_notes'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($task['notes'])): ?>
                        <div class="info-item" style="grid-column: 1 / -1;">
                            <div class="label">Additional Notes</div>
                            <div class="value" style="font-weight:400;color:var(--gray-600);">
                                <?php echo nl2br(htmlspecialchars($task['notes'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="task-sidebar">
                <!-- Volunteer Info -->
                <div class="card">
                    <div class="card-title"><i class="fas fa-user"></i> Volunteer</div>
                    <div class="volunteer-info">
                        <div class="avatar">
                            <?php if (!empty($task['volunteer_photo'])): ?>
                                <img src="<?php echo htmlspecialchars($task['volunteer_photo']); ?>" alt="<?php echo htmlspecialchars($task['volunteer_name']); ?>">
                            <?php else: ?>
                                <?php echo strtoupper(substr($task['volunteer_name'] ?? 'V', 0, 2)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="info">
                            <div class="name"><?php echo htmlspecialchars($task['volunteer_name']); ?></div>
                            <div class="sub">
                                <?php echo htmlspecialchars($task['volunteer_code']); ?>
                                <?php if (!empty($task['volunteer_phone'])): ?>
                                    • <?php echo htmlspecialchars($task['volunteer_phone']); ?>
                                <?php endif; ?>
                            </div>
                            <div class="sub" style="color:var(--gray-400);">
                                <?php if (!empty($task['volunteer_email'])): ?>
                                    <?php echo htmlspecialchars($task['volunteer_email']); ?>
                                <?php endif; ?>
                                <?php if ($task['volunteer_status'] === 'active'): ?>
                                    <span style="color:var(--success);"><i class="fas fa-circle" style="font-size:0.3rem;"></i> Active</span>
                                <?php else: ?>
                                    <span style="color:var(--danger);"><i class="fas fa-circle" style="font-size:0.3rem;"></i> <?php echo ucfirst($task['volunteer_status']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Volunteer Stats -->
                    <div class="stats-mini" style="margin-top:12px;">
                        <div class="stat">
                            <div class="number blue"><?php echo $volunteer_tasks_count['total'] ?? 0; ?></div>
                            <div class="label">Total</div>
                        </div>
                        <div class="stat">
                            <div class="number green"><?php echo $volunteer_tasks_count['completed'] ?? 0; ?></div>
                            <div class="label">Done</div>
                        </div>
                        <div class="stat">
                            <div class="number orange"><?php echo $volunteer_tasks_count['in_progress'] ?? 0; ?></div>
                            <div class="label">Progress</div>
                        </div>
                        <div class="stat">
                            <div class="number red"><?php echo $volunteer_tasks_count['pending'] ?? 0; ?></div>
                            <div class="label">Pending</div>
                        </div>
                    </div>
                </div>

                <!-- Update Status -->
                <?php if ($task['status'] !== 'completed' && $task['status'] !== 'cancelled'): ?>
                <div class="card">
                    <div class="card-title"><i class="fas fa-edit"></i> Update Status</div>
                    <form method="POST" action="" class="status-form">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        
                        <div class="form-group">
                            <label for="status">New Status</label>
                            <select name="status" id="status">
                                <option value="pending" <?php echo $task['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="notesGroup" style="display:none;">
                            <label for="completion_notes">Completion Notes</label>
                            <textarea name="completion_notes" id="completion_notes" placeholder="Add notes about completion..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="status_notes">Status Notes (Optional)</label>
                            <textarea name="status_notes" id="status_notes" placeholder="Add notes about this update..."></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Update History -->
                <?php if (count($task_updates) > 0): ?>
                <div class="card">
                    <div class="card-title"><i class="fas fa-history"></i> Activity History</div>
                    <div class="update-history">
                        <?php foreach ($task_updates as $update): ?>
                            <div class="update-item">
                                <div class="update-icon status-change">
                                    <i class="fas fa-<?php echo strpos($update['activity_type'], 'status') !== false ? 'exchange-alt' : 'file-alt'; ?>"></i>
                                </div>
                                <div class="update-content">
                                    <div class="action">
                                        <?php echo htmlspecialchars($update['description']); ?>
                                    </div>
                                    <div class="details">
                                        <?php echo htmlspecialchars($update['user_name'] ?? 'System'); ?>
                                    </div>
                                    <div class="time">
                                        <?php echo date('M d, Y H:i', strtotime($update['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
// ============================================================
// SHOW/HIDE COMPLETION NOTES
// ============================================================
document.getElementById('status').addEventListener('change', function() {
    const notesGroup = document.getElementById('notesGroup');
    if (this.value === 'completed') {
        notesGroup.style.display = 'block';
    } else {
        notesGroup.style.display = 'none';
    }
});

// ============================================================
// AUTO-HIDE ALERTS
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 500);
        }, 5000);
    });
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