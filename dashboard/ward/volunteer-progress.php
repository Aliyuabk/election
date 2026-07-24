<?php
// ============================================================
// WARD COORDINATOR - VOLUNTEER PROGRESS (COMPLETE UPDATE)
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
// GET FILTERS
// ============================================================
$volunteer_id = isset($_GET['volunteer_id']) ? (int)$_GET['volunteer_id'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ============================================================
// FETCH VOLUNTEERS AND TASKS (FIXED)
// ============================================================
$volunteers = [];
$tasks = [];
$summary = [];
$task_status_counts = [];

try {
    // Get all volunteers - FIXED: Use role_id = 15
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.user_code,
            u.phone,
            u.email,
            u.pu_id,
            u.photograph_url,
            pu.name as pu_name,
            pu.code as pu_code,
            COUNT(vt.id) as total_tasks,
            SUM(CASE WHEN vt.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
            SUM(CASE WHEN vt.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
            SUM(CASE WHEN vt.status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
            SUM(CASE WHEN vt.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_tasks,
            MAX(vt.completed_at) as last_completed_at
        FROM users u
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        LEFT JOIN volunteer_tasks vt ON vt.volunteer_id = u.id
        WHERE u.tenant_id = ? 
        AND u.ward_id = ? 
        AND u.deleted_at IS NULL
        AND u.status = 'active'
        AND u.role_id = 15
        GROUP BY u.id, u.full_name, u.user_code, u.phone, u.email, u.pu_id, u.photograph_url, pu.name, pu.code
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $volunteers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build conditions for tasks
    $conditions = "u.tenant_id = ? AND u.ward_id = ?";
    $params = [$tenant_id, $ward_id];
    
    if ($volunteer_id > 0) {
        $conditions .= " AND vt.volunteer_id = ?";
        $params[] = $volunteer_id;
    }
    
    if ($status_filter !== 'all') {
        $conditions .= " AND vt.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($date_from)) {
        $conditions .= " AND DATE(vt.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $conditions .= " AND DATE(vt.created_at) <= ?";
        $params[] = $date_to;
    }
    
    // Get tasks with volunteer info
    $stmt = $db->prepare("
        SELECT 
            vt.*,
            u.full_name as volunteer_name,
            u.user_code as volunteer_code,
            u.phone as volunteer_phone,
            u.email as volunteer_email,
            u.photograph_url as volunteer_photo,
            pu.name as volunteer_pu,
            assigned.full_name as assigned_by_name,
            assigned.user_code as assigned_by_code
        FROM volunteer_tasks vt
        JOIN users u ON vt.volunteer_id = u.id
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        LEFT JOIN users assigned ON vt.assigned_by = assigned.id
        WHERE $conditions
        ORDER BY vt.created_at DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Summary statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            COUNT(DISTINCT volunteer_id) as active_volunteers
        FROM volunteer_tasks
        WHERE tenant_id = ? AND ward_id = ?
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get task status counts for chart
    $stmt = $db->prepare("
        SELECT status, COUNT(*) as count
        FROM volunteer_tasks
        WHERE tenant_id = ? AND ward_id = ?
        GROUP BY status
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $task_status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching volunteer data: " . $e->getMessage());
    $_SESSION['flash_error'] = 'Error loading data. Please refresh the page.';
}

// ============================================================
// HANDLE TASK STATUS UPDATE
// ============================================================
$success_message = '';
$error_message = '';
$show_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_task_status') {
    $task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
    $new_status = isset($_POST['status']) ? $_POST['status'] : '';
    $completion_notes = isset($_POST['completion_notes']) ? trim($_POST['completion_notes']) : '';
    
    // CSRF Protection
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $session_token = SessionManager::get('csrf_token');
    
    if (empty($csrf_token) || $csrf_token !== $session_token) {
        $error_message = 'Security validation failed. Please try again.';
    } elseif ($task_id <= 0 || empty($new_status)) {
        $error_message = 'Invalid request.';
    } else {
        try {
            $db->beginTransaction();
            
            // Verify task belongs to volunteer in this ward
            $stmt = $db->prepare("
                SELECT vt.id, vt.volunteer_id, vt.status, u.full_name 
                FROM volunteer_tasks vt
                JOIN users u ON vt.volunteer_id = u.id
                WHERE vt.id = ? AND u.tenant_id = ? AND u.ward_id = ?
            ");
            $stmt->execute([$task_id, $tenant_id, $ward_id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$task) {
                throw new Exception('Task not found or does not belong to your ward.');
            }
            
            $update_fields = "status = ?, updated_at = NOW()";
            $params = [$new_status];
            
            if ($new_status === 'completed') {
                $update_fields .= ", completed_at = NOW(), completion_notes = ?";
                $params[] = $completion_notes;
            } elseif ($new_status === 'in_progress') {
                $update_fields .= ", started_at = COALESCE(started_at, NOW())";
            }
            
            $stmt = $db->prepare("UPDATE volunteer_tasks SET $update_fields WHERE id = ?");
            $params[] = $task_id;
            $stmt->execute($params);
            
            logActivity($user_id, 'task_status_updated', "Updated task ID: $task_id from '{$task['status']}' to '$new_status'", 'volunteer_tasks', $task_id);
            
            $db->commit();
            $success_message = "Task status updated to: " . ucfirst(str_replace('_', ' ', $new_status));
            $show_success = true;
            
            // Refresh data
            $stmt = $db->prepare("
                SELECT 
                    vt.*,
                    u.full_name as volunteer_name,
                    u.user_code as volunteer_code,
                    u.phone as volunteer_phone,
                    u.email as volunteer_email,
                    u.photograph_url as volunteer_photo,
                    pu.name as volunteer_pu,
                    assigned.full_name as assigned_by_name,
                    assigned.user_code as assigned_by_code
                FROM volunteer_tasks vt
                JOIN users u ON vt.volunteer_id = u.id
                LEFT JOIN polling_units pu ON u.pu_id = pu.id
                LEFT JOIN users assigned ON vt.assigned_by = assigned.id
                WHERE u.tenant_id = ? AND u.ward_id = ?
                ORDER BY vt.created_at DESC
                LIMIT 200
            ");
            $stmt->execute([$tenant_id, $ward_id]);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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

$page_title = 'Volunteer Progress';
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

.progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.progress-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.progress-header h2 i {
    color: var(--primary);
}
.progress-header .subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin: 2px 0 0;
}

/* Stats Row */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.stat-mini {
    background: white;
    padding: 14px 18px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    text-align: center;
    box-shadow: var(--shadow);
    transition: var(--transition);
}
.stat-mini:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}
.stat-mini .number {
    font-size: 1.4rem;
    font-weight: 700;
}
.stat-mini .number.blue { color: #3B82F6; }
.stat-mini .number.green { color: #10B981; }
.stat-mini .number.orange { color: #F59E0B; }
.stat-mini .number.purple { color: #8B5CF6; }
.stat-mini .number.red { color: #EF4444; }
.stat-mini .number.teal { color: #14B8A6; }
.stat-mini .label {
    font-size: 0.7rem;
    color: var(--gray-500);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 2px;
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

/* Filter Bar */
.filter-bar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    align-items: center;
    background: white;
    padding: 12px 16px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}
.filter-bar .filter-group {
    display: flex;
    align-items: center;
    gap: 6px;
}
.filter-bar .filter-group label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--gray-600);
}
.filter-bar select,
.filter-bar input[type="date"] {
    padding: 6px 10px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.8rem;
    background: white;
    min-width: 140px;
}
.filter-bar select:focus,
.filter-bar input[type="date"]:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(15, 76, 129, 0.1);
}
.filter-bar .filter-actions {
    display: flex;
    gap: 6px;
    margin-left: auto;
}
.filter-bar .btn-sm {
    padding: 6px 14px;
    border-radius: var(--radius);
    font-size: 0.75rem;
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.filter-bar .btn-sm.primary {
    background: var(--primary);
    color: white;
}
.filter-bar .btn-sm.primary:hover {
    background: var(--primary-dark);
}
.filter-bar .btn-sm.secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.filter-bar .btn-sm.secondary:hover {
    background: var(--gray-200);
}
.filter-bar .btn-sm.success {
    background: #D1FAE5;
    color: #065F46;
}
.filter-bar .btn-sm.success:hover {
    background: #A7F3D0;
}

/* Volunteer Grid */
.volunteer-grid {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 16px;
}

/* Volunteer List */
.volunteer-list {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    max-height: 600px;
    display: flex;
    flex-direction: column;
}
.volunteer-list .list-header {
    background: var(--gray-50);
    padding: 12px 16px;
    font-weight: 600;
    font-size: 0.8rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}
.volunteer-list .list-header .count {
    background: var(--gray-200);
    padding: 1px 10px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--gray-600);
}
.volunteer-list .list-body {
    flex: 1;
    overflow-y: auto;
    padding: 4px 0;
}
.volunteer-list .list-body::-webkit-scrollbar {
    width: 4px;
}
.volunteer-list .list-body::-webkit-scrollbar-track {
    background: var(--gray-100);
}
.volunteer-list .list-body::-webkit-scrollbar-thumb {
    background: var(--gray-300);
    border-radius: 4px;
}
.volunteer-list .list-item {
    padding: 10px 16px;
    border-bottom: 1px solid var(--gray-100);
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
    display: block;
    color: var(--gray-800);
    border-left: 3px solid transparent;
}
.volunteer-list .list-item:hover {
    background: var(--gray-50);
}
.volunteer-list .list-item.active {
    background: #EFF6FF;
    border-left-color: var(--primary);
}
.volunteer-list .list-item .volunteer-info {
    display: flex;
    align-items: center;
    gap: 10px;
}
.volunteer-list .list-item .avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    font-weight: 600;
    color: var(--gray-600);
    flex-shrink: 0;
}
.volunteer-list .list-item .avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}
.volunteer-list .list-item .name {
    font-weight: 500;
    font-size: 0.85rem;
}
.volunteer-list .list-item .sub {
    font-size: 0.7rem;
    color: var(--gray-500);
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.volunteer-list .list-item .sub .code {
    font-family: monospace;
}
.volunteer-list .list-item .progress-bar {
    height: 4px;
    background: var(--gray-200);
    border-radius: 2px;
    margin-top: 6px;
    overflow: hidden;
}
.volunteer-list .list-item .progress-bar .fill {
    height: 100%;
    border-radius: 2px;
    background: linear-gradient(90deg, var(--primary), #3B82F6);
    transition: width 0.5s ease;
}

/* Task List */
.task-list {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    max-height: 600px;
}
.task-list .list-header {
    background: var(--gray-50);
    padding: 12px 16px;
    font-weight: 600;
    font-size: 0.8rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}
.task-list .list-header .count {
    background: var(--gray-200);
    padding: 1px 10px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--gray-600);
}
.task-list .list-body {
    flex: 1;
    overflow-y: auto;
    padding: 4px 0;
}
.task-list .list-body::-webkit-scrollbar {
    width: 4px;
}
.task-list .list-body::-webkit-scrollbar-track {
    background: var(--gray-100);
}
.task-list .list-body::-webkit-scrollbar-thumb {
    background: var(--gray-300);
    border-radius: 4px;
}
.task-item {
    padding: 12px 16px;
    border-bottom: 1px solid var(--gray-100);
}
.task-item:last-child {
    border-bottom: none;
}
.task-item .task-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 8px;
}
.task-item .task-title {
    font-weight: 500;
    font-size: 0.85rem;
    flex: 1;
}
.task-item .task-meta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    font-size: 0.7rem;
    color: var(--gray-500);
    margin-top: 4px;
}
.task-item .task-meta i {
    width: 14px;
}
.task-item .task-meta .volunteer-name {
    display: flex;
    align-items: center;
    gap: 4px;
}
.task-item .task-meta .volunteer-name .mini-avatar {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--gray-200);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.4rem;
    font-weight: 600;
    color: var(--gray-600);
}
.task-item .task-meta .volunteer-name .mini-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}
.task-item .task-description {
    font-size: 0.78rem;
    color: var(--gray-600);
    margin-top: 4px;
}
.task-item .task-actions {
    display: flex;
    gap: 6px;
    margin-top: 8px;
    flex-wrap: wrap;
}
.task-item .task-actions .btn-sm {
    padding: 3px 12px;
    font-size: 0.65rem;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    font-weight: 500;
    transition: var(--transition);
}
.task-item .task-actions .btn-sm.update {
    background: #DBEAFE;
    color: #1E40AF;
}
.task-item .task-actions .btn-sm.update:hover {
    background: #BFDBFE;
}
.task-item .task-actions .btn-sm.complete {
    background: #D1FAE5;
    color: #065F46;
}
.task-item .task-actions .btn-sm.complete:hover {
    background: #A7F3D0;
}
.task-item .task-actions .btn-sm.cancel {
    background: #FEE2E2;
    color: #991B1B;
}
.task-item .task-actions .btn-sm.cancel:hover {
    background: #FECACA;
}
.task-item .task-actions .btn-sm.view {
    background: #E5E7EB;
    color: #374151;
}
.task-item .task-actions .btn-sm.view:hover {
    background: #D1D5DB;
}

/* Status Badge */
.status-badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
    flex-shrink: 0;
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
    font-size: 0.55rem;
    padding: 1px 8px;
    border-radius: 10px;
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

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--gray-400);
    width: 100%;
}
.empty-state i {
    font-size: 2.5rem;
    display: block;
    margin-bottom: 12px;
    color: var(--gray-300);
}
.empty-state p {
    font-size: 0.9rem;
    margin: 0;
}

/* Modal */
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
.modal-box .modal-body textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
}
.modal-box .modal-body textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(15, 76, 129, 0.1);
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
.modal-box .modal-actions .btn-success {
    background: #10B981;
    color: white;
}
.modal-box .modal-actions .btn-success:hover {
    background: #059669;
}
.modal-box .modal-actions .btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.modal-box .modal-actions .btn-secondary:hover {
    background: var(--gray-200);
}

/* Responsive */
@media (max-width: 1024px) {
    .volunteer-grid {
        grid-template-columns: 1fr;
    }
    .volunteer-list {
        max-height: 250px;
    }
    .task-list {
        max-height: 500px;
    }
}
@media (max-width: 768px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar .filter-group {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar .filter-actions {
        margin-left: 0;
    }
    .progress-header {
        flex-direction: column;
        align-items: flex-start;
    }
}
@media (max-width: 480px) {
    .stats-row {
        grid-template-columns: 1fr 1fr;
    }
    .filter-bar select,
    .filter-bar input[type="date"] {
        min-width: 100%;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="progress-header">
            <div>
                <h2><i class="fas fa-chart-line"></i> Volunteer Progress</h2>
                <p class="subtitle">
                    <i class="fas fa-map-marker-alt" style="color:var(--gray-400);"></i> 
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                    <?php if ($ward_id): ?>
                        • Ward ID: <?php echo htmlspecialchars($ward_id); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="assign-tasks.php" class="btn-primary-sm" style="padding:8px 16px;background:var(--primary);color:white;border-radius:var(--radius);text-decoration:none;font-size:0.8rem;font-weight:600;display:inline-flex;align-items:center;gap:6px;transition:var(--transition);">
                    <i class="fas fa-plus"></i> Assign Task
                </a>
                <a href="manage-volunteers.php" class="btn-secondary-sm" style="padding:8px 16px;border:1px solid var(--gray-200);border-radius:var(--radius);color:var(--gray-600);text-decoration:none;font-size:0.8rem;transition:var(--transition);">
                    <i class="fas fa-arrow-left"></i> Back
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

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="number blue"><?php echo number_format($summary['total_tasks'] ?? 0); ?></div>
                <div class="label">Total Tasks</div>
            </div>
            <div class="stat-mini">
                <div class="number green"><?php echo number_format($summary['completed'] ?? 0); ?></div>
                <div class="label">Completed</div>
            </div>
            <div class="stat-mini">
                <div class="number orange"><?php echo number_format($summary['in_progress'] ?? 0); ?></div>
                <div class="label">In Progress</div>
            </div>
            <div class="stat-mini">
                <div class="number purple"><?php echo number_format($summary['pending'] ?? 0); ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="stat-mini">
                <div class="number red"><?php echo number_format($summary['cancelled'] ?? 0); ?></div>
                <div class="label">Cancelled</div>
            </div>
            <div class="stat-mini">
                <div class="number teal"><?php echo number_format($summary['active_volunteers'] ?? 0); ?></div>
                <div class="label">Active Volunteers</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-group">
                <label><i class="fas fa-user"></i> Volunteer</label>
                <select id="volunteerFilter" onchange="applyFilters()">
                    <option value="0">All Volunteers</option>
                    <?php foreach ($volunteers as $vol): ?>
                        <option value="<?php echo $vol['id']; ?>" <?php echo $volunteer_id == $vol['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($vol['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-filter"></i> Status</label>
                <select id="statusFilter" onchange="applyFilters()">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> From</label>
                <input type="date" id="dateFrom" value="<?php echo htmlspecialchars($date_from); ?>" onchange="applyFilters()">
            </div>
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> To</label>
                <input type="date" id="dateTo" value="<?php echo htmlspecialchars($date_to); ?>" onchange="applyFilters()">
            </div>
            <div class="filter-actions">
                <button onclick="applyFilters()" class="btn-sm primary">
                    <i class="fas fa-search"></i> Apply
                </button>
                <button onclick="resetFilters()" class="btn-sm secondary">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <a href="volunteer-progress-export.php?volunteer_id=<?php echo $volunteer_id; ?>&status=<?php echo $status_filter; ?>" class="btn-sm success">
                    <i class="fas fa-download"></i> Export
                </a>
            </div>
        </div>

        <!-- Volunteer Grid -->
        <div class="volunteer-grid">
            <!-- Volunteer List -->
            <div class="volunteer-list">
                <div class="list-header">
                    <span><i class="fas fa-users"></i> Volunteers</span>
                    <span class="count"><?php echo count($volunteers); ?></span>
                </div>
                <div class="list-body">
                    <?php if (count($volunteers) > 0): ?>
                        <?php foreach ($volunteers as $vol): 
                            $total = $vol['total_tasks'] ?? 0;
                            $completed = $vol['completed_tasks'] ?? 0;
                            $percent = $total > 0 ? round(($completed / $total) * 100) : 0;
                            $is_active = ($volunteer_id == $vol['id']) || ($volunteer_id == 0 && $loop->first && $volunteer_id == 0);
                        ?>
                            <a href="?volunteer_id=<?php echo $vol['id']; ?>&status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
                               class="list-item <?php echo $is_active ? 'active' : ''; ?>">
                                <div class="volunteer-info">
                                    <div class="avatar">
                                        <?php if (!empty($vol['photograph_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($vol['photograph_url']); ?>" alt="">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($vol['full_name'] ?? 'U', 0, 2)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex:1;min-width:0;">
                                        <div class="name"><?php echo htmlspecialchars($vol['full_name']); ?></div>
                                        <div class="sub">
                                            <span class="code"><?php echo htmlspecialchars($vol['user_code']); ?></span>
                                            <?php if (!empty($vol['pu_name'])): ?>
                                                • <?php echo htmlspecialchars($vol['pu_name']); ?>
                                            <?php endif; ?>
                                            • <?php echo number_format($completed); ?>/<?php echo number_format($total); ?>
                                        </div>
                                    </div>
                                    <span style="font-size:0.7rem;font-weight:600;color:var(--gray-400);"><?php echo $percent; ?>%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="fill" style="width: <?php echo $percent; ?>%;"></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>No volunteers found in this ward.</p>
                            <a href="assign-volunteer.php" style="color:var(--primary);font-size:0.8rem;">Assign volunteers</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Task List -->
            <div class="task-list">
                <div class="list-header">
                    <span><i class="fas fa-list"></i> Tasks</span>
                    <span class="count"><?php echo count($tasks); ?> tasks</span>
                </div>
                <div class="list-body">
                    <?php if (count($tasks) > 0): ?>
                        <?php foreach ($tasks as $task): ?>
                            <div class="task-item">
                                <div class="task-header">
                                    <div class="task-title">
                                        <?php echo htmlspecialchars($task['title']); ?>
                                        <?php if ($task['priority'] === 'high'): ?>
                                            <span class="priority-badge high"><i class="fas fa-exclamation-circle"></i> High</span>
                                        <?php elseif ($task['priority'] === 'normal'): ?>
                                            <span class="priority-badge normal">Normal</span>
                                        <?php else: ?>
                                            <span class="priority-badge low">Low</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="status-badge <?php echo $task['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($task['description'])): ?>
                                    <div class="task-description">
                                        <?php echo htmlspecialchars(substr($task['description'], 0, 120)); ?>
                                        <?php if (strlen($task['description']) > 120): ?>...<?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="task-meta">
                                    <span class="volunteer-name">
                                        <span class="mini-avatar">
                                            <?php if (!empty($task['volunteer_photo'])): ?>
                                                <img src="<?php echo htmlspecialchars($task['volunteer_photo']); ?>" alt="">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($task['volunteer_name'] ?? 'U', 0, 2)); ?>
                                            <?php endif; ?>
                                        </span>
                                        <?php echo htmlspecialchars($task['volunteer_name']); ?>
                                    </span>
                                    <span><i class="fas fa-user-check"></i> <?php echo htmlspecialchars($task['assigned_by_name'] ?? 'System'); ?></span>
                                    <?php if (!empty($task['location'])): ?>
                                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($task['location']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($task['due_date']): ?>
                                        <span><i class="fas fa-clock"></i> Due: <?php echo date('M d, Y', strtotime($task['due_date'])); ?></span>
                                    <?php endif; ?>
                                    <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($task['created_at'])); ?></span>
                                    <?php if ($task['completed_at']): ?>
                                        <span style="color:var(--success);"><i class="fas fa-check-circle"></i> Completed: <?php echo date('M d, Y', strtotime($task['completed_at'])); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="task-actions">
                                    <?php if ($task['status'] !== 'completed' && $task['status'] !== 'cancelled'): ?>
                                        <button onclick="openUpdateModal(<?php echo $task['id']; ?>, '<?php echo $task['status']; ?>')" class="btn-sm update">
                                            <i class="fas fa-edit"></i> Update
                                        </button>
                                        <button onclick="quickComplete(<?php echo $task['id']; ?>)" class="btn-sm complete">
                                            <i class="fas fa-check"></i> Complete
                                        </button>
                                        <button onclick="quickCancel(<?php echo $task['id']; ?>)" class="btn-sm cancel">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    <?php else: ?>
                                        <a href="view-task.php?id=<?php echo $task['id']; ?>" class="btn-sm view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-tasks"></i>
                            <p>No tasks found matching the current filters.</p>
                            <a href="assign-tasks.php" style="color:var(--primary);font-size:0.8rem;">Assign a task</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Update Task Modal -->
<div class="modal-overlay" id="updateModal">
    <div class="modal-box">
        <div class="modal-title"><i class="fas fa-edit"></i> Update Task Status</div>
        <form method="POST" action="" id="updateForm">
            <input type="hidden" name="action" value="update_task_status">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="task_id" id="modalTaskId" value="">
            
            <div class="modal-body">
                <div style="margin-bottom:12px;">
                    <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:4px;">Change Status To:</label>
                    <select name="status" id="modalStatus" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:var(--radius);font-size:0.85rem;">
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div>
                    <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:4px;">Completion Notes (Optional):</label>
                    <textarea name="completion_notes" id="modalNotes" placeholder="Add notes about this update..."></textarea>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeUpdateModal()">Cancel</button>
                <button type="submit" class="btn-primary">Update Status</button>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================================
// FILTER FUNCTIONS
// ============================================================
function applyFilters() {
    const volunteer = document.getElementById('volunteerFilter').value;
    const status = document.getElementById('statusFilter').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    
    let url = '?';
    if (volunteer > 0) url += 'volunteer_id=' + volunteer + '&';
    if (status !== 'all') url += 'status=' + status + '&';
    if (dateFrom) url += 'date_from=' + dateFrom + '&';
    if (dateTo) url += 'date_to=' + dateTo + '&';
    
    // Remove trailing & or ?
    url = url.replace(/[&?]$/, '');
    if (url === '') url = '?';
    
    window.location.href = url;
}

function resetFilters() {
    window.location.href = '?';
}

// ============================================================
// MODAL FUNCTIONS
// ============================================================
function openUpdateModal(taskId, currentStatus) {
    document.getElementById('modalTaskId').value = taskId;
    document.getElementById('modalStatus').value = currentStatus;
    document.getElementById('modalNotes').value = '';
    document.getElementById('updateModal').classList.add('active');
}

function closeUpdateModal() {
    document.getElementById('updateModal').classList.remove('active');
}

function quickComplete(taskId) {
    if (confirm('Mark this task as completed?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const hiddenAction = document.createElement('input');
        hiddenAction.type = 'hidden';
        hiddenAction.name = 'action';
        hiddenAction.value = 'update_task_status';
        form.appendChild(hiddenAction);
        
        const hiddenTask = document.createElement('input');
        hiddenTask.type = 'hidden';
        hiddenTask.name = 'task_id';
        hiddenTask.value = taskId;
        form.appendChild(hiddenTask);
        
        const hiddenStatus = document.createElement('input');
        hiddenStatus.type = 'hidden';
        hiddenStatus.name = 'status';
        hiddenStatus.value = 'completed';
        form.appendChild(hiddenStatus);
        
        const hiddenCsrf = document.createElement('input');
        hiddenCsrf.type = 'hidden';
        hiddenCsrf.name = 'csrf_token';
        hiddenCsrf.value = '<?php echo htmlspecialchars($csrf_token); ?>';
        form.appendChild(hiddenCsrf);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function quickCancel(taskId) {
    if (confirm('Cancel this task?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const hiddenAction = document.createElement('input');
        hiddenAction.type = 'hidden';
        hiddenAction.name = 'action';
        hiddenAction.value = 'update_task_status';
        form.appendChild(hiddenAction);
        
        const hiddenTask = document.createElement('input');
        hiddenTask.type = 'hidden';
        hiddenTask.name = 'task_id';
        hiddenTask.value = taskId;
        form.appendChild(hiddenTask);
        
        const hiddenStatus = document.createElement('input');
        hiddenStatus.type = 'hidden';
        hiddenStatus.name = 'status';
        hiddenStatus.value = 'cancelled';
        form.appendChild(hiddenStatus);
        
        const hiddenCsrf = document.createElement('input');
        hiddenCsrf.type = 'hidden';
        hiddenCsrf.name = 'csrf_token';
        hiddenCsrf.value = '<?php echo htmlspecialchars($csrf_token); ?>';
        form.appendChild(hiddenCsrf);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal on overlay click
document.getElementById('updateModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeUpdateModal();
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