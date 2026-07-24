<?php
// ============================================================
// WARD COORDINATOR - ASSIGN TASKS TO VOLUNTEERS (COMPLETE UPDATE)
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
// FETCH WARD NAME
// ============================================================
$ward_name = 'Unknown Ward';
try {
    if ($ward_id) {
        $stmt = $db->prepare("SELECT name FROM wards WHERE id = ?");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching ward name: " . $e->getMessage());
}

// ============================================================
// FETCH VOLUNTEERS (FIXED: Uses role_id = 15)
// ============================================================
$volunteers = [];
try {
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.user_code,
            u.email,
            u.phone,
            u.pu_id,
            pu.name as pu_name,
            pu.code as pu_code,
            (SELECT COUNT(*) FROM volunteer_tasks vt 
             WHERE vt.volunteer_id = u.id AND vt.status != 'completed') as pending_tasks
        FROM users u
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        WHERE u.tenant_id = ? 
        AND u.ward_id = ? 
        AND u.deleted_at IS NULL
        AND u.status = 'active'
        AND u.role_id = 15
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $volunteers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching volunteers: " . $e->getMessage());
}

// ============================================================
// FETCH RECENT TASKS
// ============================================================
$recent_tasks = [];
try {
    $stmt = $db->prepare("
        SELECT 
            vt.*,
            u.full_name as volunteer_name,
            u.user_code,
            u.phone as volunteer_phone,
            u.email as volunteer_email,
            u.photograph_url as volunteer_photo
        FROM volunteer_tasks vt
        JOIN users u ON vt.volunteer_id = u.id
        WHERE u.tenant_id = ? 
        AND u.ward_id = ?
        AND u.deleted_at IS NULL
        ORDER BY vt.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $recent_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching recent tasks: " . $e->getMessage());
}

// ============================================================
// HANDLE TASK ASSIGNMENT
// ============================================================
$success_message = '';
$error_message = '';
$show_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_task') {
    $volunteer_id = isset($_POST['volunteer_id']) ? (int)$_POST['volunteer_id'] : 0;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $location = isset($_POST['location']) ? trim($_POST['location']) : '';
    $due_date = isset($_POST['due_date']) ? trim($_POST['due_date']) : '';
    $due_time = isset($_POST['due_time']) ? trim($_POST['due_time']) : '';
    $priority = isset($_POST['priority']) ? $_POST['priority'] : 'normal';
    $task_type = isset($_POST['task_type']) ? $_POST['task_type'] : 'general';
    
    // CSRF Protection
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $session_token = SessionManager::get('csrf_token');
    
    if (empty($csrf_token) || $csrf_token !== $session_token) {
        $error_message = 'Security validation failed. Please try again.';
    } elseif ($volunteer_id <= 0) {
        $error_message = "Please select a volunteer.";
    } elseif (empty($title)) {
        $error_message = "Please enter a task title.";
    } elseif (empty($description)) {
        $error_message = "Please enter a task description.";
    } else {
        try {
            $db->beginTransaction();
            
            // Verify volunteer exists and is in the correct ward
            $stmt = $db->prepare("
                SELECT id, full_name, pu_id, status 
                FROM users 
                WHERE id = ? AND tenant_id = ? AND ward_id = ? AND role_id = 15 AND deleted_at IS NULL
            ");
            $stmt->execute([$volunteer_id, $tenant_id, $ward_id]);
            $volunteer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$volunteer) {
                throw new Exception('Volunteer not found or does not belong to your ward.');
            }
            
            if ($volunteer['status'] !== 'active') {
                throw new Exception('Volunteer is not active. Please activate them first.');
            }
            
            // Prepare due datetime
            $due_datetime = null;
            if (!empty($due_date)) {
                $due_datetime = $due_date . ' ' . ($due_time ?: '23:59:00');
            }
            
            // Insert into volunteer_tasks table
            $stmt = $db->prepare("
                INSERT INTO volunteer_tasks (
                    volunteer_id, 
                    assigned_by, 
                    title, 
                    description, 
                    assigned_date, 
                    due_date, 
                    location, 
                    priority,
                    task_type,
                    status, 
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, 'pending', NOW(), NOW())
            ");
            $stmt->execute([
                $volunteer_id,
                $user_id,
                $title,
                $description,
                $due_datetime,
                $location,
                $priority,
                $task_type
            ]);
            
            $task_id = $db->lastInsertId();
            
            // Log activity
            logActivity($user_id, 'task_assigned', "Assigned task to volunteer: {$volunteer['full_name']} (ID: $volunteer_id) - $title", 'volunteer_tasks', $task_id);
            
            $db->commit();
            $success_message = "Task assigned successfully to {$volunteer['full_name']}!";
            $show_success = true;
            
            // Refresh data
            $stmt = $db->prepare("
                SELECT 
                    u.id,
                    u.full_name,
                    u.user_code,
                    u.email,
                    u.phone,
                    u.pu_id,
                    pu.name as pu_name,
                    pu.code as pu_code,
                    (SELECT COUNT(*) FROM volunteer_tasks vt 
                     WHERE vt.volunteer_id = u.id AND vt.status != 'completed') as pending_tasks
                FROM users u
                LEFT JOIN polling_units pu ON u.pu_id = pu.id
                WHERE u.tenant_id = ? AND u.ward_id = ? AND u.deleted_at IS NULL
                AND u.status = 'active' AND u.role_id = 15
                ORDER BY u.full_name ASC
            ");
            $stmt->execute([$tenant_id, $ward_id]);
            $volunteers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $db->prepare("
                SELECT 
                    vt.*,
                    u.full_name as volunteer_name,
                    u.user_code,
                    u.phone as volunteer_phone,
                    u.email as volunteer_email,
                    u.photograph_url as volunteer_photo
                FROM volunteer_tasks vt
                JOIN users u ON vt.volunteer_id = u.id
                WHERE u.tenant_id = ? AND u.ward_id = ? AND u.deleted_at IS NULL
                ORDER BY vt.created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$tenant_id, $ward_id]);
            $recent_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = "Error assigning task: " . $e->getMessage();
            error_log("Task assignment error: " . $e->getMessage());
        }
    }
}

// ============================================================
// HANDLE TASK STATUS UPDATE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    $completion_notes = isset($_POST['completion_notes']) ? trim($_POST['completion_notes']) : '';
    
    if ($task_id > 0 && !empty($status)) {
        try {
            $db->beginTransaction();
            
            // Verify task belongs to volunteer in this ward
            $stmt = $db->prepare("
                SELECT vt.id, vt.volunteer_id, u.full_name 
                FROM volunteer_tasks vt
                JOIN users u ON vt.volunteer_id = u.id
                WHERE vt.id = ? AND u.tenant_id = ? AND u.ward_id = ?
            ");
            $stmt->execute([$task_id, $tenant_id, $ward_id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$task) {
                throw new Exception('Task not found or does not belong to your ward.');
            }
            
            $update_fields = "status = ?";
            $params = [$status];
            
            if ($status === 'completed') {
                $update_fields .= ", completed_at = NOW(), completion_notes = ?";
                $params[] = $completion_notes;
            }
            
            $stmt = $db->prepare("UPDATE volunteer_tasks SET $update_fields, updated_at = NOW() WHERE id = ?");
            $params[] = $task_id;
            $stmt->execute($params);
            
            logActivity($user_id, 'task_status_updated', "Updated task ID: $task_id to status: $status", 'volunteer_tasks', $task_id);
            
            $db->commit();
            $success_message = "Task status updated to: " . ucfirst($status);
            $show_success = true;
            
            // Refresh data
            $stmt = $db->prepare("
                SELECT 
                    vt.*,
                    u.full_name as volunteer_name,
                    u.user_code,
                    u.phone as volunteer_phone,
                    u.email as volunteer_email,
                    u.photograph_url as volunteer_photo
                FROM volunteer_tasks vt
                JOIN users u ON vt.volunteer_id = u.id
                WHERE u.tenant_id = ? AND u.ward_id = ? AND u.deleted_at IS NULL
                ORDER BY vt.created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$tenant_id, $ward_id]);
            $recent_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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

$page_title = 'Assign Tasks';
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

/* Page Header */
.assign-task-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.assign-task-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.assign-task-header h2 i {
    color: var(--primary);
}
.assign-task-header .subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin: 2px 0 0;
}

/* Stats Bar */
.stats-bar {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.stat-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.stat-card .stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.stat-card .stat-icon.blue { background: #EFF6FF; color: #3B82F6; }
.stat-card .stat-icon.green { background: #ECFDF5; color: #10B981; }
.stat-card .stat-icon.yellow { background: #FEF3C7; color: #F59E0B; }
.stat-card .stat-icon.red { background: #FEF2F2; color: #EF4444; }
.stat-card .stat-info .stat-number {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--gray-800);
    line-height: 1.2;
}
.stat-card .stat-info .stat-label {
    font-size: 0.7rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Alerts */
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
.alert-warning {
    background: var(--warning-light);
    border-color: #FDE68A;
    color: #92400E;
}
.alert-info {
    background: var(--info-light);
    border-color: #BFDBFE;
    color: #1E40AF;
}

/* Task Form */
.task-form {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: var(--shadow);
}
.task-form .form-title {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 16px;
    color: var(--gray-700);
}
.task-form .form-title i {
    color: var(--primary);
}
.task-form .form-group {
    margin-bottom: 16px;
}
.task-form .form-group label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.task-form .form-group label .required {
    color: var(--danger);
}
.task-form .form-group input[type="text"],
.task-form .form-group input[type="date"],
.task-form .form-group input[type="time"],
.task-form .form-group textarea,
.task-form .form-group select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
    transition: var(--transition);
}
.task-form .form-group input:focus,
.task-form .form-group textarea:focus,
.task-form .form-group select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(15, 76, 129, 0.1);
}
.task-form .form-group textarea {
    resize: vertical;
    min-height: 80px;
    max-height: 150px;
    font-family: inherit;
}
.task-form .form-group .helper {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 4px;
}
.task-form .form-group select:disabled {
    background: var(--gray-100);
    cursor: not-allowed;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--gray-200);
}
.form-actions .btn-primary {
    padding: 8px 24px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: var(--radius);
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 8px;
}
.form-actions .btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(15, 76, 129, 0.3);
}
.form-actions .btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.form-actions .btn-secondary {
    padding: 8px 16px;
    background: var(--gray-100);
    color: var(--gray-600);
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-weight: 500;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
}
.form-actions .btn-secondary:hover {
    background: var(--gray-200);
}

/* Recent Tasks */
.recent-tasks {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow);
}
.recent-tasks .tasks-header {
    background: var(--gray-50);
    padding: 10px 16px;
    font-weight: 600;
    font-size: 0.8rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.recent-tasks .tasks-header .count {
    background: var(--gray-200);
    padding: 1px 10px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--gray-600);
}
.recent-tasks .tasks-body {
    max-height: 400px;
    overflow-y: auto;
}
.recent-tasks .tasks-body::-webkit-scrollbar {
    width: 4px;
}
.recent-tasks .tasks-body::-webkit-scrollbar-track {
    background: var(--gray-100);
}
.recent-tasks .tasks-body::-webkit-scrollbar-thumb {
    background: var(--gray-300);
    border-radius: 4px;
}
.task-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 12px 16px;
    border-bottom: 1px solid var(--gray-100);
    gap: 12px;
}
.task-item:last-child {
    border-bottom: none;
}
.task-item .task-info {
    flex: 1;
    min-width: 0;
}
.task-item .task-info .task-title {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--gray-800);
}
.task-item .task-info .task-description {
    font-size: 0.78rem;
    color: var(--gray-500);
    margin-top: 2px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.task-item .task-info .task-meta {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.task-item .task-info .task-meta .volunteer {
    font-weight: 500;
    color: var(--gray-600);
}
.task-item .task-info .task-meta .volunteer .avatar {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--gray-200);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.5rem;
    font-weight: 600;
    color: var(--gray-600);
    margin-right: 4px;
}
.task-item .task-info .task-meta .volunteer .avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}
.task-item .task-info .task-meta .location {
    display: flex;
    align-items: center;
    gap: 4px;
}
.task-item .task-status {
    flex-shrink: 0;
}
.task-item .task-status .badge {
    font-size: 0.6rem;
    padding: 3px 10px;
    border-radius: 20px;
    font-weight: 500;
    display: inline-block;
}
.badge-pending {
    background: var(--warning-light);
    color: #92400E;
}
.badge-in_progress {
    background: var(--info-light);
    color: #1E40AF;
}
.badge-completed {
    background: var(--success-light);
    color: #065F46;
}
.badge-cancelled {
    background: var(--danger-light);
    color: #991B1B;
}

/* Priority Badge */
.priority-high {
    color: #EF4444;
}
.priority-normal {
    color: #3B82F6;
}
.priority-low {
    color: #10B981;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--gray-400);
}
.empty-state i {
    font-size: 2.5rem;
    display: block;
    margin-bottom: 12px;
    color: var(--gray-300);
}
.empty-state p {
    margin: 0;
    font-size: 0.9rem;
}

/* Modal for task details */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
.modal-overlay.active {
    display: flex;
}
.modal-box {
    background: white;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    padding: 32px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: modalSlideIn 0.3s ease;
}
@keyframes modalSlideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.modal-box .modal-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 16px;
}
.modal-box .modal-body {
    margin-bottom: 20px;
}
.modal-box .modal-body .detail-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    border-bottom: 1px solid var(--gray-100);
    font-size: 0.85rem;
}
.modal-box .modal-body .detail-row .label {
    color: var(--gray-500);
}
.modal-box .modal-body .detail-row .value {
    font-weight: 500;
    color: var(--gray-800);
}
.modal-box .modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding-top: 16px;
    border-top: 1px solid var(--gray-200);
}
.modal-box .modal-actions .btn {
    padding: 8px 20px;
    border-radius: var(--radius);
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    border: none;
}
.modal-box .modal-actions .btn-primary {
    background: var(--primary);
    color: white;
}
.modal-box .modal-actions .btn-primary:hover {
    background: var(--primary-dark);
}
.modal-box .modal-actions .btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.modal-box .modal-actions .btn-secondary:hover {
    background: var(--gray-200);
}

/* Responsive */
@media (max-width: 992px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 768px) {
    .task-form {
        padding: 16px;
    }
    .stats-bar {
        grid-template-columns: 1fr 1fr;
    }
    .assign-task-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .form-actions {
        flex-direction: column;
    }
    .form-actions .btn-primary,
    .form-actions .btn-secondary {
        width: 100%;
        justify-content: center;
    }
}
@media (max-width: 480px) {
    .stats-bar {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="assign-task-header">
            <div>
                <h2><i class="fas fa-tasks"></i> Assign Tasks to Volunteers</h2>
                <p class="subtitle">
                    <i class="fas fa-map-marker-alt" style="color:var(--gray-400);"></i> 
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                    <?php if ($ward_id): ?>
                        • Ward ID: <?php echo htmlspecialchars($ward_id); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="volunteer-progress.php" class="btn-secondary-sm" style="padding:6px 14px;border:1px solid var(--gray-200);border-radius:var(--radius);color:var(--gray-600);text-decoration:none;font-size:0.8rem;transition:var(--transition);">
                    <i class="fas fa-chart-line"></i> Progress
                </a>
                <a href="manage-volunteers.php" class="btn-secondary-sm" style="padding:6px 14px;border:1px solid var(--gray-200);border-radius:var(--radius);color:var(--gray-600);text-decoration:none;font-size:0.8rem;transition:var(--transition);">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Stats Bar -->
        <?php 
        $total_volunteers = count($volunteers);
        $pending_tasks = 0;
        $in_progress_tasks = 0;
        $completed_tasks = 0;
        
        foreach ($recent_tasks as $task) {
            if ($task['status'] === 'pending') $pending_tasks++;
            elseif ($task['status'] === 'in_progress') $in_progress_tasks++;
            elseif ($task['status'] === 'completed') $completed_tasks++;
        }
        ?>
        <div class="stats-bar">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $total_volunteers; ?></div>
                    <div class="stat-label">Total Volunteers</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $pending_tasks; ?></div>
                    <div class="stat-label">Pending Tasks</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-spinner"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $in_progress_tasks; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $completed_tasks; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
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

        <!-- Task Form -->
        <div class="task-form">
            <div class="form-title"><i class="fas fa-plus-circle" style="color:var(--primary);"></i> Create New Task</div>
            <form method="POST" action="" id="taskForm">
                <input type="hidden" name="action" value="assign_task">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="form-group">
                    <label>Select Volunteer <span class="required">*</span></label>
                    <select name="volunteer_id" id="volunteer_id" required>
                        <option value="">-- Select Volunteer --</option>
                        <?php foreach ($volunteers as $volunteer): ?>
                            <option value="<?php echo $volunteer['id']; ?>">
                                <?php echo htmlspecialchars($volunteer['full_name']); ?> 
                                (<?php echo htmlspecialchars($volunteer['user_code']); ?>)
                                <?php if (!empty($volunteer['pu_name'])): ?>
                                    - <?php echo htmlspecialchars($volunteer['pu_name']); ?>
                                <?php endif; ?>
                                <?php if (($volunteer['pending_tasks'] ?? 0) > 0): ?>
                                    - <?php echo $volunteer['pending_tasks']; ?> pending
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($volunteers)): ?>
                        <div class="helper" style="color:var(--danger);">
                            <i class="fas fa-exclamation-circle"></i> 
                            No volunteers available. Please <a href="assign-volunteer.php" style="color:var(--primary);">assign volunteers</a> first.
                        </div>
                    <?php else: ?>
                        <div class="helper" id="volunteerInfo"></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Task Title <span class="required">*</span></label>
                    <input type="text" name="title" id="title" placeholder="Enter task title..." required>
                </div>

                <div class="form-group">
                    <label>Task Description <span class="required">*</span></label>
                    <textarea name="description" id="description" placeholder="Describe the task in detail..." required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" id="location" placeholder="Enter location (optional)">
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority" id="priority">
                            <option value="low">Low</option>
                            <option value="normal" selected>Normal</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Task Type</label>
                        <select name="task_type" id="task_type">
                            <option value="general">General</option>
                            <option value="field">Field Work</option>
                            <option value="office">Office Work</option>
                            <option value="reporting">Reporting</option>
                            <option value="community">Community Engagement</option>
                            <option value="logistics">Logistics</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" name="due_date" id="due_date" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Due Time</label>
                    <input type="time" name="due_time" id="due_time">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary" id="assignBtn" <?php echo empty($volunteers) ? 'disabled' : ''; ?>>
                        <i class="fas fa-plus"></i> Assign Task
                    </button>
                    <button type="reset" class="btn-secondary">
                        <i class="fas fa-undo"></i> Clear
                    </button>
                </div>
            </form>
        </div>

        <!-- Recent Tasks -->
        <div class="recent-tasks">
            <div class="tasks-header">
                <span><i class="fas fa-history" style="color:var(--primary);"></i> Recent Tasks</span>
                <span class="count"><?php echo count($recent_tasks); ?></span>
            </div>
            <div class="tasks-body">
                <?php if (count($recent_tasks) > 0): ?>
                    <?php foreach ($recent_tasks as $task): ?>
                        <div class="task-item">
                            <div class="task-info">
                                <div class="task-title">
                                    <?php echo htmlspecialchars($task['title']); ?>
                                   // Replace lines around 1096 with this:
                                    <?php if (isset($task['priority']) && $task['priority'] === 'high'): ?>
                                        <span class="priority-badge high"><i class="fas fa-exclamation-circle"></i> High</span>
                                    <?php elseif (isset($task['priority']) && $task['priority'] === 'normal'): ?>
                                        <span class="priority-badge normal">Normal</span>
                                    <?php elseif (isset($task['priority']) && $task['priority'] === 'low'): ?>
                                        <span class="priority-badge low">Low</span>
                                    <?php endif; ?>
                                </div>
                                <div class="task-description">
                                    <?php echo htmlspecialchars(substr($task['description'] ?? '', 0, 100)) . (strlen($task['description'] ?? '') > 100 ? '...' : ''); ?>
                                </div>
                                <div class="task-meta">
                                    <span class="volunteer">
                                        <span class="avatar">
                                            <?php if (!empty($task['volunteer_photo'])): ?>
                                                <img src="<?php echo htmlspecialchars($task['volunteer_photo']); ?>" alt="">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($task['volunteer_name'] ?? 'U', 0, 2)); ?>
                                            <?php endif; ?>
                                        </span>
                                        <?php echo htmlspecialchars($task['volunteer_name']); ?>
                                    </span>
                                    <?php if (!empty($task['location'])): ?>
                                        <span class="location">
                                            <i class="fas fa-map-pin"></i> <?php echo htmlspecialchars($task['location']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span>
                                        <i class="far fa-calendar-alt"></i> 
                                        <?php echo date('M d, Y', strtotime($task['created_at'])); ?>
                                    </span>
                                    <?php if ($task['due_date']): ?>
                                        <span>
                                            <i class="far fa-clock"></i> 
                                            Due: <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="task-status">
                                <span class="badge badge-<?php echo str_replace('_', '', $task['status'] ?? 'pending'); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $task['status'] ?? 'Pending')); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-tasks"></i>
                        <p>No tasks assigned yet. Create your first task above!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
// ============================================================
// JAVASCRIPT FUNCTIONS
// ============================================================

// Update volunteer info when selected
document.getElementById('volunteer_id').addEventListener('change', function() {
    const infoDiv = document.getElementById('volunteerInfo');
    const selectedOption = this.options[this.selectedIndex];
    
    if (selectedOption && selectedOption.value) {
        const text = selectedOption.text;
        const pendingMatch = text.match(/(\d+) pending/);
        if (pendingMatch) {
            const pending = parseInt(pendingMatch[1]);
            if (pending > 0) {
                infoDiv.innerHTML = '<span style="color:var(--warning);"><i class="fas fa-info-circle"></i> This volunteer has ' + pending + ' pending task(s).</span>';
            } else {
                infoDiv.innerHTML = '<span style="color:var(--success);"><i class="fas fa-check-circle"></i> Volunteer is available for new tasks.</span>';
            }
        } else {
            infoDiv.innerHTML = '';
        }
    } else {
        infoDiv.innerHTML = '';
    }
});

// Set minimum due date to today
document.getElementById('due_date').min = new Date().toISOString().split('T')[0];

// Validate form
document.getElementById('taskForm').addEventListener('submit', function(e) {
    const volunteer = document.getElementById('volunteer_id').value;
    const title = document.getElementById('title').value.trim();
    const description = document.getElementById('description').value.trim();
    
    if (!volunteer) {
        e.preventDefault();
        alert('Please select a volunteer.');
        document.getElementById('volunteer_id').focus();
        return false;
    }
    if (!title) {
        e.preventDefault();
        alert('Please enter a task title.');
        document.getElementById('title').focus();
        return false;
    }
    if (!description) {
        e.preventDefault();
        alert('Please enter a task description.');
        document.getElementById('description').focus();
        return false;
    }
    return true;
});

// Auto-hide alerts after 5 seconds
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
    
    // Trigger initial volunteer info update
    document.getElementById('volunteer_id').dispatchEvent(new Event('change'));
});

// ============================================================
// SIDEBAR TOGGLE (from parent)
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

// Sidebar dropdowns
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

// Profile dropdown
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

// Preloader
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